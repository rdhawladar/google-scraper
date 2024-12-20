import React from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import '@testing-library/jest-dom';
import KeywordList from '../components/KeywordList';
import { AuthContext } from '../contexts/AuthContext';

// Mock axios directly instead of importing
jest.mock('../utils/axios', () => ({
  __esModule: true,
  default: {
    get: jest.fn(),
    post: jest.fn(),
    delete: jest.fn(),
    interceptors: {
      request: { use: jest.fn(), eject: jest.fn() },
      response: { use: jest.fn(), eject: jest.fn() }
    }
  }
}));

// Get the mocked axios
const mockedAxios = jest.requireMock('../utils/axios').default;

// Mock data
const mockKeywords = [
  {
    id: 1,
    keyword: 'test keyword 1',
    status: 'completed',
    created_at: '2024-01-01T00:00:00.000Z',
    updated_at: '2024-01-01T00:00:00.000Z'
  },
  {
    id: 2,
    keyword: 'test keyword 2',
    status: 'pending',
    created_at: '2024-01-01T00:00:00.000Z',
    updated_at: '2024-01-01T00:00:00.000Z'
  }
];

// Mock AuthContext
const mockAuthContext = {
  user: { id: 1, name: 'Test User', email: 'test@example.com' },
  token: 'mock-token',
  login: jest.fn(),
  register: jest.fn(),
  logout: jest.fn(),
  isLoading: false
};

describe('KeywordList Component', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders keyword list successfully', async () => {
    mockedAxios.get.mockResolvedValueOnce({ data: { data: mockKeywords } });

    render(
      <AuthContext.Provider value={mockAuthContext}>
        <KeywordList />
      </AuthContext.Provider>
    );

    // Initial loading state
    expect(screen.getByText('Loading keywords...')).toBeInTheDocument();

    // Wait for data to load
    await waitFor(() => {
      expect(screen.getByText('test keyword 1')).toBeInTheDocument();
      expect(screen.getByText('test keyword 2')).toBeInTheDocument();
    });
  });

  it('displays search results in modal', async () => {
    mockedAxios.get.mockResolvedValueOnce({ data: { data: mockKeywords } });

    render(
      <AuthContext.Provider value={mockAuthContext}>
        <KeywordList />
      </AuthContext.Provider>
    );

    await waitFor(() => {
      expect(screen.getByText('test keyword 1')).toBeInTheDocument();
    });

    const viewResultsButton = screen.getByText('View Results');
    await act(async () => {
      fireEvent.click(viewResultsButton);
    });

    await waitFor(() => {
      expect(mockedAxios.get).toHaveBeenCalledWith('/search-results/1', {
        headers: {
          'Authorization': 'Bearer mock-token'
        }
      });
    });
  });

  it('filters keywords by status', async () => {
    mockedAxios.get.mockResolvedValueOnce({ data: { data: mockKeywords } });

    render(
      <AuthContext.Provider value={mockAuthContext}>
        <KeywordList />
      </AuthContext.Provider>
    );

    await waitFor(() => {
      expect(screen.getByText('test keyword 1')).toBeInTheDocument();
      expect(screen.getByText('test keyword 2')).toBeInTheDocument();
    });

    const statusSelect = screen.getByRole('combobox');
    await act(async () => {
      fireEvent.change(statusSelect, { target: { value: 'completed' } });
    });

    await waitFor(() => {
      expect(screen.getByText('test keyword 1')).toBeInTheDocument();
      expect(screen.queryByText('test keyword 2')).not.toBeInTheDocument();
    });
  });

  it('searches keywords by text', async () => {
    mockedAxios.get.mockResolvedValueOnce({ data: { data: mockKeywords } });

    render(
      <AuthContext.Provider value={mockAuthContext}>
        <KeywordList />
      </AuthContext.Provider>
    );

    await waitFor(() => {
      expect(screen.getByText('test keyword 1')).toBeInTheDocument();
      expect(screen.getByText('test keyword 2')).toBeInTheDocument();
    });

    const searchInput = screen.getByPlaceholderText('Search keywords...');
    await act(async () => {
      fireEvent.change(searchInput, { target: { value: 'keyword 1' } });
    });

    await waitFor(() => {
      expect(screen.getByText('test keyword 1')).toBeInTheDocument();
      expect(screen.queryByText('test keyword 2')).not.toBeInTheDocument();
    });
  });
});
