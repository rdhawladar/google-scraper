import React, { useEffect, useState } from "react";
import {
  Table,
  Badge,
  Card,
  Alert,
  Button,
  Modal,
  ListGroup,
  Spinner,
  Form,
  InputGroup,
  Row,
  Col,
  Dropdown,
  Pagination,
  Tab,
  Tabs,
} from "react-bootstrap";
import { useAuth } from "../contexts/AuthContext";
import axios from "../utils/axios";

interface ParsedResult {
  title: string | null;
  url: string | null;
  description: string | null;
}

interface SearchResult {
  id: number;
  keyword_id: number;
  total_ads: number;
  total_links: number;
  organic_results: Array<{
    title: string;
    url: string;
    snippet: string;
  }>;
  html_cache: string;
  status: string;
  error_message?: string;
  scraped_at: string;
  created_at: string;
  updated_at: string;
}

interface Keyword {
  id: number;
  keyword: string;
  status: "pending" | "processing" | "completed" | "failed";
  results: SearchResult[] | { error: string };
  created_at: string;
  updated_at: string;
}

const STATUS_OPTIONS = [
  { value: "", label: "All Status" },
  { value: "pending", label: "Pending" },
  { value: "processing", label: "Processing" },
  { value: "completed", label: "Completed" },
  { value: "failed", label: "Failed" },
];

const ITEMS_PER_PAGE = 20;

export default function KeywordList() {
  const [keywords, setKeywords] = useState<Keyword[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [selectedKeyword, setSelectedKeyword] = useState<Keyword | null>(null);
  const [searchResults, setSearchResults] = useState<SearchResult | null>(null);
  const [loadingResults, setLoadingResults] = useState(false);
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [sortBy, setSortBy] = useState<"created" | "updated">("created");
  const [sortOrder, setSortOrder] = useState<"asc" | "desc">("desc");
  const [currentPage, setCurrentPage] = useState(1);
  const { token } = useAuth();

  const fetchKeywords = async () => {
    try {
      const response = await axios.get("/keywords", {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });
      setKeywords(response.data);
      setError("");
    } catch (err: any) {
      setError(
        err.response?.data?.message ||
          "An error occurred while fetching keywords"
      );
    } finally {
      setLoading(false);
    }
  };

  const fetchSearchResults = async (keywordId: number) => {
    setLoadingResults(true);
    try {
      const response = await axios.get(`/search-results/${keywordId}`, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });
      setSearchResults(response.data);
    } catch (err: any) {
      setError(
        err.response?.data?.message ||
          "An error occurred while fetching search results"
      );
    } finally {
      setLoadingResults(false);
    }
  };

  const handleRetry = async (keywordId: number) => {
    try {
      await axios.post(`/keywords/${keywordId}/retry`, null, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });
      fetchKeywords();
    } catch (err: any) {
      setError(
        err.response?.data?.message ||
          "An error occurred while retrying the keyword"
      );
    }
  };

  const handleShowResults = async (keyword: Keyword) => {
    setSelectedKeyword(keyword);
    setSearchResults(null);
    await fetchSearchResults(keyword.id);
  };

  const handleCloseModal = () => {
    setSelectedKeyword(null);
    setSearchResults(null);
  };

  const handleSort = (field: "created" | "updated") => {
    if (sortBy === field) {
      setSortOrder(sortOrder === "asc" ? "desc" : "asc");
    } else {
      setSortBy(field);
      setSortOrder("desc");
    }
  };

  const getSortIcon = (field: "created" | "updated") => {
    if (sortBy !== field) return "↕️";
    return sortOrder === "asc" ? "↑" : "↓";
  };

  useEffect(() => {
    fetchKeywords();
    const interval = setInterval(fetchKeywords, 10000);
    return () => clearInterval(interval);
  }, [token]);

  useEffect(() => {
    // Reset to first page when filters change
    setCurrentPage(1);
  }, [searchTerm, statusFilter, sortBy, sortOrder]);

  const filteredKeywords = keywords
    .filter(
      (keyword) =>
        keyword.keyword.toLowerCase().includes(searchTerm.toLowerCase()) &&
        (statusFilter ? keyword.status === statusFilter : true)
    )
    .sort((a, b) => {
      const dateA = new Date(
        sortBy === "created" ? a.created_at : a.updated_at
      );
      const dateB = new Date(
        sortBy === "created" ? b.created_at : b.updated_at
      );
      return sortOrder === "asc"
        ? dateA.getTime() - dateB.getTime()
        : dateB.getTime() - dateA.getTime();
    });

  const totalPages = Math.ceil(filteredKeywords.length / ITEMS_PER_PAGE);
  const paginatedKeywords = filteredKeywords.slice(
    (currentPage - 1) * ITEMS_PER_PAGE,
    currentPage * ITEMS_PER_PAGE
  );

  const getStatusBadge = (status: string) => {
    const variants: { [key: string]: string } = {
      pending: "secondary",
      processing: "info",
      completed: "success",
      failed: "danger",
    };
    return <Badge bg={variants[status] || "secondary"}>{status}</Badge>;
  };

  const renderPagination = () => {
    const items = [];
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

    if (endPage - startPage + 1 < maxVisiblePages) {
      startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }

    // First page
    if (startPage > 1) {
      items.push(
        <Pagination.First key="first" onClick={() => setCurrentPage(1)} />,
        <Pagination.Ellipsis key="ellipsis1" />
      );
    }

    // Page numbers
    for (let page = startPage; page <= endPage; page++) {
      items.push(
        <Pagination.Item
          key={page}
          active={page === currentPage}
          onClick={() => setCurrentPage(page)}
        >
          {page}
        </Pagination.Item>
      );
    }

    // Last page
    if (endPage < totalPages) {
      items.push(
        <Pagination.Ellipsis key="ellipsis2" />,
        <Pagination.Last
          key="last"
          onClick={() => setCurrentPage(totalPages)}
        />
      );
    }

    return <Pagination>{items}</Pagination>;
  };

  const parseHtmlResults = (htmlContent: string): ParsedResult[] => {
    console.log("Raw HTML:", htmlContent); // Debug log

    const parser = new DOMParser();
    const doc = parser.parseFromString(htmlContent, "text/html");
    const results: ParsedResult[] = [];

    // Try different selectors that Google might be using
    const searchResults = doc.querySelectorAll(".g, .MjjYud, .Gx5Zad");
    console.log("Found search results:", searchResults.length); // Debug log

    searchResults.forEach((result, index) => {
      // Try multiple possible selectors for each element
      const titleElement = result.querySelector("h3, .DKV0Md");
      const linkElement = result.querySelector("a[href], .yuRUbf a");
      const descElement = result.querySelector(".VwiC3b, .lEBKkf, .s3v9rd");

      if (titleElement || linkElement || descElement) {
        results.push({
          title: titleElement?.textContent?.trim() || null,
          url: linkElement?.getAttribute("href") || null,
          description: descElement?.textContent?.trim() || null,
        });
      }
    });

    return results;
  };

  if (loading) {
    return <div>Loading keywords...</div>;
  }

  return (
    <>
      <Card className="shadow-sm">
        <Card.Body>
          <Card.Title className="d-flex justify-content-between align-items-center mb-4">
            <span>Keywords</span>
            <small className="text-muted">
              {filteredKeywords.length} of {keywords.length} keywords
            </small>
          </Card.Title>

          {error && <Alert variant="danger">{error}</Alert>}

          <Row className="mb-3">
            <Col md={6}>
              <InputGroup>
                <InputGroup.Text>
                  <i className="bi bi-search"></i>
                </InputGroup.Text>
                <Form.Control
                  type="text"
                  placeholder="Search keywords..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                />
              </InputGroup>
            </Col>
            <Col md={3}>
              <Form.Select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
              >
                {STATUS_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </Form.Select>
            </Col>
            <Col md={3}>
              <Dropdown>
                <Dropdown.Toggle
                  variant="outline-secondary"
                  id="sort-dropdown"
                  className="w-100"
                >
                  Sort by: {sortBy === "created" ? "Created" : "Updated"}{" "}
                  {sortOrder === "asc" ? "↑" : "↓"}
                </Dropdown.Toggle>
                <Dropdown.Menu>
                  <Dropdown.Item onClick={() => handleSort("created")}>
                    Created Date {getSortIcon("created")}
                  </Dropdown.Item>
                  <Dropdown.Item onClick={() => handleSort("updated")}>
                    Updated Date {getSortIcon("updated")}
                  </Dropdown.Item>
                </Dropdown.Menu>
              </Dropdown>
            </Col>
          </Row>

          {filteredKeywords.length === 0 ? (
            <Alert variant="info">
              No keywords found matching your criteria.
            </Alert>
          ) : (
            <>
              <Table responsive hover>
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Keyword</th>
                    <th>Status</th>
                    <th
                      style={{ cursor: "pointer" }}
                      onClick={() => handleSort("created")}
                    >
                      Created {sortBy === "created" && getSortIcon("created")}
                    </th>
                    <th
                      style={{ cursor: "pointer" }}
                      onClick={() => handleSort("updated")}
                    >
                      Updated {sortBy === "updated" && getSortIcon("updated")}
                    </th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {paginatedKeywords.map((keyword) => (
                    <tr key={keyword.id}>
                      <td>{keyword.id}</td>
                      <td>{keyword.keyword}</td>
                      <td>{getStatusBadge(keyword.status)}</td>
                      <td>{new Date(keyword.created_at).toLocaleString()}</td>
                      <td>{new Date(keyword.updated_at).toLocaleString()}</td>
                      <td>
                        {keyword.status === "completed" && (
                          <Button
                            variant="primary"
                            size="sm"
                            onClick={() => handleShowResults(keyword)}
                          >
                            View Results
                          </Button>
                        )}
                        {keyword.status === "failed" && (
                          <Button
                            variant="warning"
                            size="sm"
                            onClick={() => handleRetry(keyword.id)}
                            className="ms-2"
                          >
                            Retry
                          </Button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </Table>

              <div className="d-flex justify-content-between align-items-center mt-3">
                <small className="text-muted">
                  Showing {(currentPage - 1) * ITEMS_PER_PAGE + 1} to{" "}
                  {Math.min(
                    currentPage * ITEMS_PER_PAGE,
                    filteredKeywords.length
                  )}{" "}
                  of {filteredKeywords.length} keywords
                </small>
                {totalPages > 1 && renderPagination()}
              </div>
            </>
          )}
        </Card.Body>
      </Card>

      <Modal show={!!selectedKeyword} onHide={handleCloseModal} size="lg">
        <Modal.Header closeButton>
          <Modal.Title>
            Search Results for "{selectedKeyword?.keyword}"
          </Modal.Title>
        </Modal.Header>
        <Modal.Body>
          {loadingResults ? (
            <div className="text-center p-4">
              <Spinner animation="border" role="status">
                <span className="visually-hidden">Loading...</span>
              </Spinner>
            </div>
          ) : searchResults ? (
            <>
              <Tabs defaultActiveKey="statistics" className="mb-3">
                <Tab eventKey="statistics" title="Statistics">
                  <div className="mb-4">
                    <ListGroup horizontal className="mb-3">
                      <ListGroup.Item>
                        <strong>Total Ads:</strong> {searchResults.total_ads}
                      </ListGroup.Item>
                      <ListGroup.Item>
                        <strong>Total Links:</strong>{" "}
                        {searchResults.total_links}
                      </ListGroup.Item>
                      <ListGroup.Item>
                        <strong>Scraped At:</strong>{" "}
                        {new Date(searchResults.scraped_at).toLocaleString()}
                      </ListGroup.Item>
                    </ListGroup>
                  </div>
                </Tab>

                <Tab eventKey="html" title="HTML Cache">
                  <div className="parsed-results-container">
                    <ListGroup variant="flush">
                      {searchResults?.html_cache ? (
                        <>
                          {parseHtmlResults(searchResults.html_cache).length >
                          0 ? (
                            <ListGroup variant="flush">
                              {parseHtmlResults(searchResults.html_cache).map(
                                (result, index) => (
                                  <ListGroup.Item key={index}>
                                    {(result.title || result.url) && (
                                      <h5
                                        style={{ wordBreak: "break-word" }}
                                      >
                                        <a
                                          href={result.url || "#"}
                                          target="_blank"
                                          rel="noopener noreferrer"
                                        >
                                          {result.title || result.url}
                                        </a>
                                      </h5>
                                    )}
                                    <p className="mt-2 mb-0">
                                      {result.description}
                                    </p>
                                  </ListGroup.Item>
                                )
                              )}
                            </ListGroup>
                          ) : (
                            <Alert variant="info">
                              No results could be parsed from the HTML cache.
                              The HTML structure might have changed.
                            </Alert>
                          )}
                          <div className="mt-3">
                            <Button
                              variant="outline-secondary"
                              size="sm"
                              onClick={() => {
                                console.log(
                                  "Raw HTML for debugging:",
                                  searchResults.html_cache
                                );
                              }}
                            >
                              Log Raw HTML (for debugging)
                            </Button>
                          </div>
                        </>
                      ) : (
                        <Alert variant="warning">No HTML cache available</Alert>
                      )}
                    </ListGroup>
                  </div>
                </Tab>
              </Tabs>
            </>
          ) : (
            <Alert variant="warning">No search results available.</Alert>
          )}
        </Modal.Body>
        <Modal.Footer>
          <Button variant="secondary" onClick={handleCloseModal}>
            Close
          </Button>
        </Modal.Footer>
      </Modal>
    </>
  );
}
