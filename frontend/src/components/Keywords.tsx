import React from 'react';
import { Container, Row, Col } from 'react-bootstrap';
import KeywordUpload from './KeywordUpload';
import KeywordList from './KeywordList';

const Keywords: React.FC = () => {
  return (
    <Container className="py-4">
      <Row className="mb-4">
        <Col>
          <KeywordUpload />
        </Col>
      </Row>
      <Row>
        <Col>
          <KeywordList />
        </Col>
      </Row>
    </Container>
  );
};

export default Keywords;
