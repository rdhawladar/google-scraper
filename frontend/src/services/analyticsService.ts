import api from './api';

export interface ScraperStats {
  performance: {
    success_rate: number;
    total_requests: number;
    circuit_status: string;
    failure_reasons: Record<string, number>;
  };
  keywords: {
    total: number;
    completed: number;
    failed: number;
    pending: number;
  };
  results: {
    total: number;
    by_type: Record<string, number>;
    avg_per_keyword?: number;
  };
  recent_failures: Array<{
    id: number;
    keyword: string;
    updated_at: string;
  }>;
}

export interface HourlyStat {
  hour: string;
  count: number;
}

export interface FailureAnalysis {
  daily_failures: Array<{
    date: string;
    count: number;
  }>;
  failure_reasons: Record<string, number>;
}

const analyticsService = {
  getStats: async (): Promise<ScraperStats> => {
    const response = await api.get('/api/analytics/stats');
    return response.data;
  },

  getHourlyStats: async (): Promise<HourlyStat[]> => {
    const response = await api.get('/api/analytics/hourly');
    return response.data;
  },

  getFailureAnalysis: async (): Promise<FailureAnalysis> => {
    const response = await api.get('/api/analytics/failures');
    return response.data;
  }
};

export default analyticsService;
