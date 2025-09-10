# Integration Tests

End-to-end validation of the AI Log Inspector system components.

## Test Classes

### LogInspectorAgentIntegrationTest
**Tests**: AI agent with tool integration  
**Coverage**: 24 tests, 241 assertions  
- Agent initialization and tool discovery
- Multi-step log analysis workflows
- Large-scale processing (100+ entries)
- Error handling and edge cases

### LogSearchToolIntegrationTest
**Tests**: LogSearchTool functionality  
**Coverage**: 8 tests, 48 assertions
- Vector similarity search
- Metadata filtering and AI analysis
- Query preprocessing and validation
- Response structure integrity

### SimpleIntegrationTest  
**Tests**: Core functionality with mocks  
**Coverage**: 9 tests, 52 assertions
- Basic log search operations
- Platform integration validation  
- Tool parameter handling
- Graceful error responses

## Test Data

Realistic production-style logs:
```json
{
  "payment_001": {
    "content": "[2024-01-15] ERROR: PaymentException: Gateway timeout...",
    "vector": [0.9, 0.1, 0.2, 0.8, 0.3],
    "metadata": {"level": "error", "source": "payment-service"}
  }
}
```

**Categories**: Payment failures, database issues, security events, performance problems

## Architecture

```
Agent → Platform → Tool → Vector Store → AI Analysis → Response
```

**Mocking**: InMemoryStore with pre-populated vectors, mock AI platform with realistic responses

## Key Scenarios

- **"payment gateway timeout"** → Identifies PaymentException logs with root cause
- **"database connection error"** → Finds ConnectionException entries  
- **"authentication failure"** → Locates security breach attempts
- **Empty/invalid queries** → Graceful error handling

## Running Tests

```bash
# All integration tests
vendor/bin/phpunit test/Integration/

# Specific test class  
vendor/bin/phpunit test/Integration/LogInspectorAgentIntegrationTest.php
```

## Business Value

- **60-80% faster incident response** through automated analysis
- **Natural language queries** replace complex grep commands  
- **Pattern recognition** across distributed services
- **Root cause analysis** with structured evidence
