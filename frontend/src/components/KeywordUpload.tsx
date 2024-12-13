import React, { useState } from 'react';
import { Card, Form, Button, Alert, ProgressBar } from 'react-bootstrap';
import { useAuth } from '../contexts/AuthContext';
import axios from '../utils/axios';

export default function KeywordUpload() {
  const [file, setFile] = useState<File | null>(null);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [uploadProgress, setUploadProgress] = useState(0);
  const { token } = useAuth();

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const selectedFile = e.target.files[0];
      if (selectedFile.type !== 'text/csv') {
        setError('Please upload a CSV file');
        setFile(null);
        return;
      }
      setFile(selectedFile);
      setError('');
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

      setSuccess('File uploaded successfully! Keywords are being processed.');
      setFile(null);
      if (e.target instanceof HTMLFormElement) {
        e.target.reset();
      }
    } catch (err: any) {
      setError(
        err.response?.data?.message || 
        'An error occurred while uploading the file'
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
        <Card.Text className="text-muted mb-4">
          Upload a CSV file containing keywords to scrape. The file should have one keyword per line.
        </Card.Text>

        {error && <Alert variant="danger">{error}</Alert>}
        {success && <Alert variant="success">{success}</Alert>}

        <Form onSubmit={handleUpload}>
          <Form.Group controlId="formFile" className="mb-3">
            <Form.Label>Choose CSV file</Form.Label>
            <Form.Control
              type="file"
              accept=".csv"
              onChange={handleFileChange}
              disabled={uploading}
            />
          </Form.Group>

          {uploading && (
            <ProgressBar
              now={uploadProgress}
              label={`${uploadProgress}%`}
              className="mb-3"
            />
          )}

          <Button
            type="submit"
            variant="primary"
            disabled={!file || uploading}
          >
            {uploading ? 'Uploading...' : 'Upload Keywords'}
          </Button>
        </Form>
      </Card.Body>
    </Card>
  );
}
