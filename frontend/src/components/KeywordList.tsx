import React, { useEffect, useState } from 'react';
import { Table, Badge, Card, Alert, Button, Modal, ListGroup, Spinner } from 'react-bootstrap';
import { useAuth } from '../contexts/AuthContext';
import axios from '../utils/axios';

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
  status: string;
  error_message?: string;
  scraped_at: string;
  created_at: string;
  updated_at: string;
}

interface Keyword {
  id: number;
  keyword: string;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  results: SearchResult[] | { error: string };
  created_at: string;
  updated_at: string;
}

export default function KeywordList() {
  const [keywords, setKeywords] = useState<Keyword[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedKeyword, setSelectedKeyword] = useState<Keyword | null>(null);
  const [searchResults, setSearchResults] = useState<SearchResult | null>(null);
  const [loadingResults, setLoadingResults] = useState(false);
  const { token } = useAuth();

  const fetchKeywords = async () => {
    try {
      const response = await axios.get('/keywords', {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });
      setKeywords(response.data);
      setError('');
    } catch (err: any) {
      setError(
        err.response?.data?.message || 
        'An error occurred while fetching keywords'
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
        'An error occurred while fetching search results'
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
      fetchKeywords(); // Refresh the list
    } catch (err: any) {
      setError(
        err.response?.data?.message || 
        'An error occurred while retrying the keyword'
      );
    }
  };

  const handleShowResults = async (keyword: Keyword) => {
    setSelectedKeyword(keyword);
    setSearchResults(null); // Clear previous results
    await fetchSearchResults(keyword.id);
  };

  const handleCloseModal = () => {
    setSelectedKeyword(null);
    setSearchResults(null);
  };

  useEffect(() => {
    fetchKeywords();
    // Poll for updates every 10 seconds
    const interval = setInterval(fetchKeywords, 10000);
    return () => clearInterval(interval);
  }, [token]);

  const getStatusBadge = (status: string) => {
    const variants: { [key: string]: string } = {
      pending: 'secondary',
      processing: 'info',
      completed: 'success',
      failed: 'danger',
    };
    return <Badge bg={variants[status] || 'secondary'}>{status}</Badge>;
  };

  if (loading) {
    return <div>Loading keywords...</div>;
  }

  return (
    <>
      <Card className="shadow-sm">
        <Card.Body>
          <Card.Title>Keywords</Card.Title>
          
          {error && <Alert variant="danger">{error}</Alert>}

          {keywords.length === 0 ? (
            <Alert variant="info">
              No keywords found. Upload a CSV file to start scraping.
            </Alert>
          ) : (
            <Table responsive hover>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Keyword</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th>Updated</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {keywords.map((keyword) => (
                  <tr key={keyword.id}>
                    <td>{keyword.id}</td>
                    <td>{keyword.keyword}</td>
                    <td>{getStatusBadge(keyword.status)}</td>
                    <td>{new Date(keyword.created_at).toLocaleString()}</td>
                    <td>{new Date(keyword.updated_at).toLocaleString()}</td>
                    <td>
                      {keyword.status === 'completed' && (
                        <Button
                          variant="primary"
                          size="sm"
                          onClick={() => handleShowResults(keyword)}
                        >
                          View Results
                        </Button>
                      )}
                      {keyword.status === 'failed' && (
                        <Button
                          variant="warning"
                          size="sm"
                          onClick={() => handleRetry(keyword.id)}
                        >
                          Retry
                        </Button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </Table>
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
              <div className="mb-4">
                <h6>Statistics</h6>
                <ListGroup horizontal className="mb-3">
                  <ListGroup.Item>
                    <strong>Total Ads:</strong> {searchResults.total_ads}
                  </ListGroup.Item>
                  <ListGroup.Item>
                    <strong>Total Links:</strong> {searchResults.total_links}
                  </ListGroup.Item>
                  <ListGroup.Item>
                    <strong>Scraped At:</strong>{' '}
                    {new Date(searchResults.scraped_at).toLocaleString()}
                  </ListGroup.Item>
                </ListGroup>
              </div>

              <h6>Organic Results</h6>
              <ListGroup variant="flush">
                {searchResults.organic_results?.map((result, index) => (
                  <ListGroup.Item key={index}>
                    <h5>
                      <a href={result.url} target="_blank" rel="noopener noreferrer">
                        {result.title}
                      </a>
                    </h5>
                    <div className="text-muted small">{result.url}</div>
                    {result.snippet && (
                      <p className="mt-2 mb-0">{result.snippet}</p>
                    )}
                  </ListGroup.Item>
                ))}
              </ListGroup>
            </>
          ) : (
            <Alert variant="warning">
              No search results found for this keyword.
            </Alert>
          )}
        </Modal.Body>
      </Modal>
    </>
  );
}
