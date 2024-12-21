import React, { useEffect, useState } from "react";
import { Card, Row, Col, Alert } from "react-bootstrap";
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  BarChart,
  Bar,
} from "recharts";
import analyticsService, {
  PerformanceStats,
  HourlyStats,
  FailureStats,
  KeywordStats,
} from "../services/analyticsService";

const Analytics: React.FC = () => {
  const [performance, setPerformance] = useState<PerformanceStats | null>(null);
  const [hourlyStats, setHourlyStats] = useState<HourlyStats[]>([]);
  const [failures, setFailures] = useState<FailureStats | null>(null);
  const [keywordStats, setKeywordStats] = useState<KeywordStats[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchData = async () => {
    try {
      setLoading(true);
      const [statsData, hourlyData, failuresData, keywordsData] =
        await Promise.all([
          analyticsService.getStats(),
          analyticsService.getHourlyStats(),
          analyticsService.getFailureAnalysis(),
          analyticsService.getKeywordStats(),
        ]);

      setPerformance(statsData.performance);
      setHourlyStats(hourlyData);
      setFailures(failuresData);
      setKeywordStats(keywordsData);
      setError(null);
    } catch (err) {
      setError("Failed to load analytics data");
      console.error("Analytics error:", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
    const interval = setInterval(fetchData, 30000); // Refresh every 30 seconds
    return () => clearInterval(interval);
  }, []);

  if (loading) {
    return <div className="text-center p-5">Loading analytics...</div>;
  }

  if (error) {
    return <Alert variant="danger">{error}</Alert>;
  }

  return (
    <div className="p-4">
      {/* Performance Overview */}
      <Row className="mb-4">
        <Col md={3}>
          <Card>
            <Card.Body>
              <Card.Title>Success Rate</Card.Title>
              <h2 className="mb-0">{performance?.success_rate}%</h2>
            </Card.Body>
          </Card>
        </Col>
        <Col md={3}>
          <Card>
            <Card.Body>
              <Card.Title>Circuit Breaker</Card.Title>
              <h2
                className={`mb-0 ${
                  performance?.circuit_breaker === "open"
                    ? "text-danger"
                    : "text-success"
                }`}
              >
                {performance?.circuit_breaker}
              </h2>
            </Card.Body>
          </Card>
        </Col>
        <Col md={3}>
          <Card>
            <Card.Body>
              <Card.Title>Total Processed</Card.Title>
              <h2 className="mb-0">{performance?.total_processed}</h2>
            </Card.Body>
          </Card>
        </Col>
        <Col md={3}>
          <Card>
            <Card.Body>
              <Card.Title>Success/Failed</Card.Title>
              <h2 className="mb-0">
                {performance?.total_success}/{performance?.total_failed}
              </h2>
            </Card.Body>
          </Card>
        </Col>
      </Row>

      {/* Hourly Stats Chart */}
      <Card className="mb-4">
        <Card.Body>
          <Card.Title>Hourly Performance</Card.Title>
          <div style={{ height: "300px" }}>
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={hourlyStats}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis
                  dataKey="timestamp"
                  tickFormatter={(value) =>
                    new Date(value).toLocaleTimeString()
                  }
                />
                <YAxis />
                <Tooltip
                  labelFormatter={(value) => new Date(value).toLocaleString()}
                  formatter={(value: number) => [value, "Requests"]}
                />
                <Area
                  type="monotone"
                  dataKey="success"
                  stackId="1"
                  stroke="#82ca9d"
                  fill="#82ca9d"
                />
                <Area
                  type="monotone"
                  dataKey="failed"
                  stackId="1"
                  stroke="#ff8042"
                  fill="#ff8042"
                />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </Card.Body>
      </Card>

      <Row className="mb-4">
        {/* Recent Failures */}
        <Col md={6}>
          <Card>
            <Card.Body>
              <Card.Title>Recent Failures</Card.Title>
              <div style={{ height: "300px" }}>
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={failures?.recent_failures}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="reason" />
                    <YAxis />
                    <Tooltip />
                    <Bar dataKey="count" fill="#ff8042" />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </Card.Body>
          </Card>
        </Col>

        {/* Top Keywords */}
        <Col md={6}>
          <Card>
            <Card.Body>
              <Card.Title>Top Keywords Performance</Card.Title>
              <div style={{ height: "300px" }}>
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={keywordStats}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="keyword" />
                    <YAxis />
                    <Tooltip />
                    <Bar dataKey="successful" stackId="a" fill="#82ca9d" />
                    <Bar dataKey="failed" stackId="a" fill="#ff8042" />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </Card.Body>
          </Card>
        </Col>
      </Row>

      {/* Daily Failures Trend */}
      <Card>
        <Card.Body>
          <Card.Title>Daily Failures Trend</Card.Title>
          <div style={{ height: "300px" }}>
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={failures?.daily_failures}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis
                  dataKey="date"
                  tickFormatter={(value) =>
                    new Date(value).toLocaleDateString()
                  }
                />
                <YAxis />
                <Tooltip
                  labelFormatter={(value) =>
                    new Date(value).toLocaleDateString()
                  }
                />
                <Area
                  type="monotone"
                  dataKey="count"
                  stroke="#ff8042"
                  fill="#ff8042"
                />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </Card.Body>
      </Card>
    </div>
  );
};

export default Analytics;
