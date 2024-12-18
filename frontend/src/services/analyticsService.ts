import api from './api';

export interface PerformanceStats {
  success_rate: number;
  circuit_breaker: string;
  total_processed: number;
  total_success: number;
  total_failed: number;
}

export interface RecentActivity {
  success?: number;
  failed?: number;
  pending?: number;
}

export interface HourlyStats {
  timestamp: string;
  success: number;
  failed: number;
  total: number;
}

export interface FailureStats {
  daily_failures: Array<{
    date: string;
    count: number;
  }>;
  recent_failures: Array<{
    reason: string;
    count: number;
  }>;
}

export interface KeywordStats {
  keyword: string;
  total_attempts: number;
  successful: number;
  failed: number;
  last_attempt: string;
}

class AnalyticsService {
  async getStats() {
    const response = await api.get('/api/analytics/stats');
    return response.data;
  }

  async getHourlyStats() {
    const response = await api.get('/api/analytics/hourly');
    return response.data.hourly_stats;
  }

  async getFailureAnalysis() {
    const response = await api.get('/api/analytics/failures');
    return response.data;
  }

  async getKeywordStats() {
    const response = await api.get('/api/keywords');
    return response.data.keyword_stats;
  }
}

export default new AnalyticsService();
