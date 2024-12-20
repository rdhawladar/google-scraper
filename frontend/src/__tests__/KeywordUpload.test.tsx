import React from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import '@testing-library/jest-dom';
import KeywordUpload from '../components/KeywordUpload';
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

// Mock AuthContext
const mockAuthContext = {
  user: { id: 1, name: 'Test User', email: 'test@example.com' },
  token: 'mock-token',
  login: jest.fn(),
  register: jest.fn(),
  logout: jest.fn(),
  isLoading: false
};

describe('KeywordUpload Component', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('handles file upload successfully', async () => {
    mockedAxios.post.mockResolvedValueOnce({ 
      data: { 
        message: 'Keywords uploaded successfully'
      } 
    });

    render(
      <AuthContext.Provider value={mockAuthContext}>
        <KeywordUpload />
      </AuthContext.Provider>
    );

    // Find and trigger the file input
    const fileInput = screen.getByLabelText(/Choose a CSV file containing keywords/i);
    const file = new File(['keyword\ntest keyword 1'], 'keywords.csv', { type: 'text/csv' });
    
    await act(async () => {
      Object.defineProperty(fileInput, 'files', {
        value: [file]
      });
      fireEvent.change(fileInput);
    });

    // Wait for the button to be enabled
    await waitFor(() => {
      const uploadButton = screen.getByRole('button', { name: /Upload/i });
      expect(uploadButton).not.toBeDisabled();
    });

    // Submit the form
    const uploadButton = screen.getByRole('button', { name: /Upload/i });
    await act(async () => {
      fireEvent.click(uploadButton);
    });

    await waitFor(() => {
      expect(mockedAxios.post).toHaveBeenCalledWith('/keywords/upload', expect.any(FormData), {
        headers: {
          'Authorization': 'Bearer mock-token',
          'Content-Type': 'multipart/form-data'
        },
        onUploadProgress: expect.any(Function)
      });
      expect(screen.getByText(/Keywords uploaded successfully/i)).toBeInTheDocument();
    });
  });

  it('validates file type', async () => {
    render(
      <AuthContext.Provider value={mockAuthContext}>
        <KeywordUpload />
      </AuthContext.Provider>
    );

    const fileInput = screen.getByLabelText(/Choose a CSV file containing keywords/i);
    const file = new File(['test'], 'test.txt', { type: 'text/plain' });
    
    await act(async () => {
      Object.defineProperty(fileInput, 'files', {
        value: [file]
      });
      fireEvent.change(fileInput);
    });

    expect(screen.getByText('Please upload a CSV file')).toBeInTheDocument();
  });

  it('validates keyword count', async () => {
    render(
      <AuthContext.Provider value={mockAuthContext}>
        <KeywordUpload />
      </AuthContext.Provider>
    );

    const fileInput = screen.getByLabelText(/Choose a CSV file containing keywords/i);
    const keywords = Array(101).fill('keyword').join('\n');
    const file = new File([keywords], 'keywords.csv', { type: 'text/csv' });
    
    await act(async () => {
      Object.defineProperty(fileInput, 'files', {
        value: [file]
      });
      fireEvent.change(fileInput);
    });

    expect(screen.getByText(/Maximum 100 keywords allowed/)).toBeInTheDocument();
  });

  it('shows error message on upload failure', async () => {
    mockedAxios.post.mockRejectedValueOnce({
      response: {
        data: {
          message: 'Upload failed'
        }
      }
    });

    render(
      <AuthContext.Provider value={mockAuthContext}>
        <KeywordUpload />
      </AuthContext.Provider>
    );

    const fileInput = screen.getByLabelText(/Choose a CSV file containing keywords/i);
    const file = new File(['keyword\ntest keyword'], 'keywords.csv', { type: 'text/csv' });
    
    await act(async () => {
      Object.defineProperty(fileInput, 'files', {
        value: [file]
      });
      fireEvent.change(fileInput);
    });

    // Wait for the button to be enabled
    await waitFor(() => {
      const uploadButton = screen.getByRole('button', { name: /Upload/i });
      expect(uploadButton).not.toBeDisabled();
    });

    // Submit the form
    const uploadButton = screen.getByRole('button', { name: /Upload/i });
    await act(async () => {
      fireEvent.click(uploadButton);
    });

    await waitFor(() => {
      expect(screen.getByText(/Upload failed/i)).toBeInTheDocument();
    });
  });
});
