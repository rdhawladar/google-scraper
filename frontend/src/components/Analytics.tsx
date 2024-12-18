import React, { useEffect, useState } from 'react';
import { Card, Row, Col, Badge, Table, Alert } from 'react-bootstrap';
import {
  LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
  PieChart, Pie, Cell
} from 'recharts';
import analyticsService, { ScraperStats, HourlyStat, FailureAnalysis } from '../services/analyticsService';

const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884d8'];

const Analytics: React.FC = () => {
  const [stats, setStats] = useState<ScraperStats | null>(null);
  const [hourlyStats, setHourlyStats] = useState<HourlyStat[]>([]);
  const [failureAnalysis, setFailureAnalysis] = useState<FailureAnalysis | null>(null);
  const [error, setError] = useState<string | null>(null);

  const fetchData = async () => {
    try {
      const [statsData, hourlyData, failureData] = await Promise.all([
        analyticsService.getStats(),
        analyticsService.getHourlyStats(),
        analyticsService.getFailureAnalysis()
      ]);
      setStats(statsData);
      setHourlyStats(hourlyData);
      setFailureAnalysis(failureData);
    } catch (err) {
      setError('Failed to load analytics data');
      console.error(err);
    }
  };

  useEffect(() => {
    fetchData();
    const interval = setInterval(fetchData, 30000); // Refresh every 30 seconds
    return () => clearInterval(interval);
  }, []);

  if (error) {
    return <Alert variant="danger">{error}</Alert>;
  }

  if (!stats || !failureAnalysis) {
    return <div>Loading analytics...</div>;
  }

  const getCircuitStatusColor = (status: string) => {
    switch (status) {
      case 'open': return 'danger';
      case 'half_open': return 'warning';
      case 'closed': return 'success';
      default: return 'secondary';
    }
  };

  const formatPercentage = (value: number) => `${(value * 100).toFixed(1)}%`;

  return (
    <div className="p-4">
      <h2 className="mb-4">Scraper Analytics</h2>

      {/* Performance Overview */}
      <Row className="mb-4">
        <Col md={3}>
          <Card>
            <Card.Body>
              <Card.Title>Success Rate</Card.Title>
              <h3>{formatPercentage(stats.performance.success_rate)}</h3>
              <Badge bg={getCircuitStatusColor(stats.performance.circuit_status)}>
                Circuit: {stats.performance.circuit_status}
              </Badge>
            </Card.Body>
          </Card>
        </Col>
        <Col md={3}>
          <Card>
            <Card.Body>
              <Card.Title>Total Requests</Card.Title>
              <h3>{stats.performance.total_requests}</h3>
            </Card.Body>
          </Card>
        </Col>
        <Col md={3}>
          <Card>
            <Card.Body>
              <Card.Title>Keywords</Card.Title>
              <h3>{stats.keywords.total}</h3>
              <div>
                <Badge bg="success">Completed: {stats.keywords.completed}</Badge>{' '}
                <Badge bg="warning">Pending: {stats.keywords.pending}</Badge>{' '}
                <Badge bg="danger">Failed: {stats.keywords.failed}</Badge>
              </div>
            </Card.Body>
          </Card>
        </Col>
        <Col md={3}>
          <Card>
            <Card.Body>
              <Card.Title>Results</Card.Title>
              <h3>{stats.results.total}</h3>
              {stats.results.avg_per_keyword && (
                <small>Avg. {stats.results.avg_per_keyword.toFixed(1)} per keyword</small>
              )}
            </Card.Body>
          </Card>
        </Col>
      </Row>

      {/* Charts */}
      <Row className="mb-4">
        <Col md={8}>
          <Card>
            <Card.Body>
              <Card.Title>Hourly Results</Card.Title>
              <div style={{ height: '300px' }}>
                <ResponsiveContainer>
                  <LineChart data={hourlyStats}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis 
                      dataKey="hour"
                      tickFormatter={(value) => new Date(value).toLocaleTimeString()}
                    />
                    <YAxis />
                    <Tooltip
                      labelFormatter={(value) => new Date(value).toLocaleString()}
                    />
                    <Line type="monotone" dataKey="count" stroke="#8884d8" />
                  </LineChart>
                </ResponsiveContainer>
              </div>
            </Card.Body>
          </Card>
        </Col>
        <Col md={4}>
          <Card>
            <Card.Body>
              <Card.Title>Result Types</Card.Title>
              <div style={{ height: '300px' }}>
                <ResponsiveContainer>
                  <PieChart>
                    <Pie
                      data={Object.entries(stats.results.by_type).map(([name, value]) => ({
                        name,
                        value
                      }))}
                      cx="50%"
                      cy="50%"
                      outerRadius={80}
                      dataKey="value"
                      label={({ name, percent }) => `${name} (${(percent * 100).toFixed(0)}%)`}
                    >
                      {Object.keys(stats.results.by_type).map((_, index) => (
                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                      ))}
                    </Pie>
                    <Tooltip />
                  </PieChart>
                </ResponsiveContainer>
              </div>
            </Card.Body>
          </Card>
        </Col>
      </Row>

      {/* Recent Failures */}
      <Row className="mb-4">
        <Col md={12}>
          <Card>
            <Card.Body>
              <Card.Title>Recent Failures</Card.Title>
              <Table striped bordered hover>
                <thead>
                  <tr>
                    <th>Keyword</th>
                    <th>Failed At</th>
                    <th>Reason</th>
                  </tr>
                </thead>
                <tbody>
                  {stats.recent_failures.map((failure) => (
                    <tr key={failure.id}>
                      <td>{failure.keyword}</td>
                      <td>{new Date(failure.updated_at).toLocaleString()}</td>
                      <td>
                        {Object.entries(failureAnalysis.failure_reasons)
                          .find(([_, count]) => count > 0)?.[0] || 'Unknown'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            </Card.Body>
          </Card>
        </Col>
      </Row>
    </div>
  );
};

export default Analytics;
