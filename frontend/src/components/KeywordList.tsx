import React, { useEffect, useState } from 'react';
import { Table, Badge, Card, Alert, Button, Modal, ListGroup } from 'react-bootstrap';
import { useAuth } from '../contexts/AuthContext';
import axios from '../utils/axios';

interface SearchResult {
  title: string;
  url: string;
  snippet: string | null;
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
  };

  const handleCloseModal = () => {
    setSelectedKeyword(null);
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
          {selectedKeyword?.results && 'error' in selectedKeyword.results ? (
            <Alert variant="danger">
              Error: {selectedKeyword.results.error}
            </Alert>
          ) : (
            <ListGroup variant="flush">
              {(selectedKeyword?.results as SearchResult[])?.map((result, index) => (
                <ListGroup.Item key={index}>
                  <h5>
                    <a href={result.url} target="_blank" rel="noopener noreferrer">
                      {result.title}
                    </a>
                  </h5>
                  <div className="text-muted small">{result.url}</div>
                  {result.snippet && <p className="mt-2 mb-0">{result.snippet}</p>}
                </ListGroup.Item>
              ))}
            </ListGroup>
          )}
        </Modal.Body>
      </Modal>
    </>
  );
}
