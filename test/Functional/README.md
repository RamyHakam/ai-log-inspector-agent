# Functional Tests with Ollama

Real-world functional tests using Ollama for local AI inference without API costs.

## 🎯 **Purpose**

Validates end-to-end AI Log Inspector functionality with actual AI models:
- **Real AI Analysis** - Tests with actual language models
- **User Story Validation** - DevOps engineer workflows  
- **Performance Testing** - Response times and quality
- **Production Readiness** - Real-world scenario validation

## 🔧 **Setup**

### Install Ollama
```bash
# macOS
brew install ollama

# Linux
curl -fsSL https://ollama.com/install.sh | sh
```

### Start Ollama and Pull Model
```bash
# Start Ollama service
ollama serve

# Pull lightweight model for testing  
ollama pull llama3.2:1b  # 1.3GB, fast inference with tool calling
# Alternative: ollama pull phi:2.7b  # Larger but good quality
```

### Verify Setup
```bash
# Test Ollama API
curl -X POST http://localhost:11434/api/generate \
  -H "Content-Type: application/json" \
  -d '{"model": "llama3.2:1b", "prompt": "Hello", "stream": false}'
```

## 🧪 **Test Class**

### **LogInspectorFunctionalTest**
**Tests**: Comprehensive AI Log Inspector system validation
**Coverage**: 12 tests with realistic PHP logs and real AI analysis
- Payment system incident analysis (Stripe, PayPal)
- Database performance and connection issues
- Security threat investigation (auth failures, attacks)
- Application error analysis (PHP exceptions)
- System performance monitoring (CPU, memory)
- Incident timeline reconstruction
- Platform factory integration testing
- DevOps user story workflows
- Error handling and edge cases
- Log statistics validation
- Category-specific analysis
- Overall system integration

## 🚀 **Running Tests**

### Local Development
```bash
# Start Ollama first
ollama serve

# Run all functional tests
vendor/bin/phpunit test/Functional/ --testdox

# Run the main functional test class
vendor/bin/phpunit test/Functional/LogInspectorFunctionalTest.php

# Run with custom model
OLLAMA_MODEL=llama3.2:1b vendor/bin/phpunit test/Functional/

# Run with custom Ollama URL
OLLAMA_URL=http://localhost:11434 vendor/bin/phpunit test/Functional/
```

### GitHub Actions
Tests automatically run in CI with Ollama Docker container:
```yaml
# Installs Ollama, pulls llama3.2:1b model, runs functional tests
- uses: ./.github/workflows/functional-tests.yml
```

## 📊 **Expected Results**

### **Performance Expectations**
- **Response Time**: < 10-15 seconds per test (local Ollama)
- **Quality**: Substantial responses (50+ characters)
- **Relevance**: Context-appropriate analysis
- **Consistency**: Stable responses across runs

### **Test Coverage**
- **User Stories**: Real DevOps workflows
- **AI Quality**: Analysis relevance and accuracy
- **Error Handling**: Graceful failure scenarios
- **Performance**: Response time benchmarks

## 🔍 **Test Scenarios**

### **DevOps Workflows**
- "The checkout service is returning 500 errors. What should I check first?"
- "I'm seeing intermittent database connection timeouts. What could cause this?"  
- "Multiple failed login attempts from the same IP. Is this a security concern?"
- "Our API response times increased from 200ms to 2 seconds. Where do I start?"

### **Quality Validation**
- Response relevance to the question
- Actionable guidance provided
- Technical accuracy 
- Appropriate response length
- No generic "I don't know" responses

## 🎛️ **Configuration**

### **Environment Variables**
```bash
# Ollama configuration
export OLLAMA_URL=http://localhost:11434
export OLLAMA_MODEL=llama3.2:1b

# Alternative models for different use cases
export OLLAMA_MODEL=phi:2.7b           # Good quality, larger
export OLLAMA_MODEL=llama2:7b-chat     # Better quality, slower
export OLLAMA_MODEL=mistral:7b         # Good balance
```
### **Adding New Functional Tests**
1. Create test method in appropriate class
2. Use real user scenarios and questions
3. Test with actual Ollama AI responses
4. Validate response quality and relevance
5. Add performance expectations