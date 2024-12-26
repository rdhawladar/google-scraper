import React, { useState } from 'react';
import { Card, Form, Button, Alert, ProgressBar } from 'react-bootstrap';
import { useAuth } from '../contexts/AuthContext';
import axios from '../utils/axios';

const MAX_KEYWORDS_PER_UPLOAD = 100;

export default function KeywordUpload() {
  const [file, setFile] = useState<File | null>(null);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [uploadProgress, setUploadProgress] = useState(0);
  const [keywordCount, setKeywordCount] = useState(0);
  const { token } = useAuth();

  const validateCsvContent = async (file: File): Promise<number> => {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      
      reader.onload = (e) => {
        const text = e.target?.result as string;
        const lines = text.split('\n').filter(line => line.trim());
        
        // Remove header if it exists
        const hasHeader = lines.length > 0 && isNaN(Number(lines[0].trim()));
        const count = hasHeader ? lines.length - 1 : lines.length;
        
        if (count > MAX_KEYWORDS_PER_UPLOAD) {
          reject(new Error(`Maximum ${MAX_KEYWORDS_PER_UPLOAD} keywords allowed per upload. Found: ${count} keywords.`));
        } else if (count === 0) {
          reject(new Error('The CSV file is empty. Please upload a file containing keywords.'));
        } else {
          resolve(count);
        }
      };
      
      reader.onerror = () => {
        reject(new Error('Error reading file'));
      };
      
      reader.readAsText(file);
    });
  };

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const selectedFile = e.target.files[0];
      
      if (selectedFile.type !== 'text/csv') {
        setError('Please upload a CSV file');
        setFile(null);
        setKeywordCount(0);
        return;
      }

      try {
        const count = await validateCsvContent(selectedFile);
        setKeywordCount(count);
        setFile(selectedFile);
        setError('');
      } catch (err: any) {
        setError(err.message);
        setFile(null);
        setKeywordCount(0);
      }
    }
  };

  const handleUpload = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!file) {
      setError('Please select a file to upload');
      return;
    }

    const formData = new FormData();
    formData.append('file', file);

    try {
      setUploading(true);
      setError('');
      setSuccess('');
      
      const response = await axios.post('/keywords/upload', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
          Authorization: `Bearer ${token}`,
        },
        onUploadProgress: (progressEvent) => {
          const progress = progressEvent.total
            ? Math.round((progressEvent.loaded * 100) / progressEvent.total)
            : 0;
          setUploadProgress(progress);
        },
      });

      setSuccess(response.data.message);
      setFile(null);
      setKeywordCount(0);
      
    } catch (err: any) {
      setError(
        err.response?.data?.message ||
        err.response?.data?.file?.[0] ||
        'Error uploading file'
      );
    } finally {
      setUploading(false);
      setUploadProgress(0);
    }
  };

  return (
    <Card className="shadow-sm">
      <Card.Body>
        <Card.Title>Upload Keywords</Card.Title>
        
        <Form onSubmit={handleUpload}>
          <Form.Group controlId="formFile" className="mb-3">
            <Form.Label>Choose a CSV file containing keywords</Form.Label>
            <Form.Control
              type="file"
              accept=".csv"
              onChange={handleFileChange}
              disabled={uploading}
            />
            <Form.Text className="text-muted">
              Maximum {MAX_KEYWORDS_PER_UPLOAD} keywords allowed per upload.
              {keywordCount > 0 && ` Found: ${keywordCount} keywords in file.`}
            </Form.Text>
          </Form.Group>

          {error && <Alert variant="danger">{error}</Alert>}
          {success && <Alert variant="success">{success}</Alert>}

          {uploading && (
            <ProgressBar
              now={uploadProgress}
              label={`${uploadProgress}%`}
              className="mb-3"
            />
          )}

          <Button
            variant="primary"
            type="submit"
            disabled={!file || uploading || keywordCount === 0}
          >
            {uploading ? 'Uploading...' : 'Upload'}
          </Button>
        </Form>
      </Card.Body>
    </Card>
  );
}
