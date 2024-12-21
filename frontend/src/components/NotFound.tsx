import { Container, Row, Col, Button } from 'react-bootstrap';
import { useNavigate } from 'react-router-dom';

const NotFound = () => {
  const navigate = useNavigate();

  return (
    <Container className="mt-5">
      <Row className="justify-content-center text-center">
        <Col md={6}>
          <h1 className="display-1">404</h1>
          <h2 className="mb-4">Page Not Found</h2>
          <p className="lead mb-4">
            Oops! The page you're looking for doesn't exist.
          </p>
          <Button 
            variant="primary" 
            onClick={() => navigate('/')}
            className="me-3"
          >
            Go to Home
          </Button>
          <Button 
            variant="outline-secondary" 
            onClick={() => navigate(-1)}
          >
            Go Back
          </Button>
        </Col>
      </Row>
    </Container>
  );
};

export default NotFound;
