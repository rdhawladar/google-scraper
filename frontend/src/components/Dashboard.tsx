import React from 'react';
import { Container, Navbar, Nav, Button, Tab, Tabs } from 'react-bootstrap';
import { useAuth } from '../contexts/AuthContext';
import { useNavigate } from 'react-router-dom';
import Keywords from './Keywords';
import Analytics from './Analytics';

export default function Dashboard() {
  const { logout, user } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    try {
      await logout();
      navigate('/login');
    } catch (error) {
      console.error('Failed to log out:', error);
    }
  };

  return (
    <div>
      <Navbar bg="dark" variant="dark" expand="lg">
        <Container>
          <Navbar.Brand href="#home">Google Scraper</Navbar.Brand>
          <Nav>
            <Nav.Item className="d-flex align-items-center">
              <span className="text-light me-3">Welcome, {user?.name}</span>
              <span className="text-light me-3">|</span>
              <span className="text-light me-3">{user?.email}</span>
              <Button variant="outline-danger" onClick={handleLogout}>
                Logout
              </Button>
            </Nav.Item>
          </Nav>
        </Container>
      </Navbar>

      <Container fluid className="p-0">
        <Tabs defaultActiveKey="keywords" className="mb-3">
          <Tab eventKey="keywords" title="Keywords">
            <Keywords />
          </Tab>
          <Tab eventKey="analytics" title="Analytics">
            <Analytics />
          </Tab>
        </Tabs>
      </Container>
    </div>
  );
}
