import React, { useState, useRef, useEffect } from 'react';
import Layout from '@theme/Layout';
import styles from './playground.module.css';

interface Message {
  id: string;
  role: 'user' | 'assistant' | 'system';
  content: string;
  timestamp: Date;
  duration?: number;
  evidenceLogs?: LogEntry[];
}

interface LogEntry {
  id: string;
  level: string;
  message: string;
  timestamp: string;
  category?: string;
  context?: Record<string, any>;
}

interface ApiResponse {
  success: boolean;
  content: string;
  evidence_logs?: LogEntry[];
  duration_ms?: number;
  model?: string;
  error?: string;
}

interface PlatformConfig {
  id: string;
  name: string;
  icon: string;
  brainModels: { value: string; label: string }[];
  embeddingModels: { value: string; label: string }[];
  placeholder: string;
  helpUrl: string;
  requiresApiKey: boolean;
}

// API URL - can be configured via query param: ?api=https://your-api.com
const getApiUrl = (): string => {
  if (typeof window !== 'undefined') {
    const params = new URLSearchParams(window.location.search);
    const customApi = params.get('api');
    if (customApi) {
      return customApi;
    }
  }
  return 'http://localhost:8080';
};

const PLATFORMS: PlatformConfig[] = [
  {
    id: 'openai',
    name: 'OpenAI',
    icon: '🟢',
    brainModels: [
      { value: 'gpt-4o-mini', label: 'GPT-4o Mini (Recommended)' },
      { value: 'gpt-4o', label: 'GPT-4o' },
      { value: 'gpt-4-turbo', label: 'GPT-4 Turbo' },
      { value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo' },
    ],
    embeddingModels: [
      { value: 'text-embedding-3-small', label: 'text-embedding-3-small (Recommended)' },
      { value: 'text-embedding-3-large', label: 'text-embedding-3-large' },
      { value: 'text-embedding-ada-002', label: 'text-embedding-ada-002' },
    ],
    placeholder: 'sk-...',
    helpUrl: 'https://platform.openai.com/api-keys',
    requiresApiKey: true,
  },
  {
    id: 'anthropic',
    name: 'Anthropic',
    icon: '🟤',
    brainModels: [
      { value: 'claude-sonnet-4-20250514', label: 'Claude Sonnet 4 (Recommended)' },
      { value: 'claude-3-7-sonnet-latest', label: 'Claude 3.7 Sonnet' },
      { value: 'claude-3-5-haiku-latest', label: 'Claude 3.5 Haiku (Fast)' },
    ],
    embeddingModels: [
      { value: 'none', label: 'No embedding (keyword search)' },
    ],
    placeholder: 'sk-ant-...',
    helpUrl: 'https://console.anthropic.com/settings/keys',
    requiresApiKey: true,
  },
  {
    id: 'ollama',
    name: 'Ollama (Local)',
    icon: '🦙',
    brainModels: [
      { value: 'llama3.1', label: 'Llama 3.1 (Recommended)' },
      { value: 'llama3.1:70b', label: 'Llama 3.1 70B' },
      { value: 'mistral', label: 'Mistral' },
      { value: 'codellama', label: 'CodeLlama' },
      { value: 'qwen2.5', label: 'Qwen 2.5' },
    ],
    embeddingModels: [
      { value: 'nomic-embed-text', label: 'Nomic Embed Text (Recommended)' },
      { value: 'mxbai-embed-large', label: 'MXBai Embed Large' },
      { value: 'all-minilm', label: 'All-MiniLM' },
    ],
    placeholder: 'http://localhost:11434',
    helpUrl: 'https://ollama.ai/download',
    requiresApiKey: false,
  },
];

const QUICK_QUESTIONS = [
  { label: ' order number req_bb002  issue why', icon: '📦' },
  { label: 'what is the email  status of this order ord_9002', icon: '📧' },
  { label: 'the user with name  "dave"  reset password failed', icon: '👤' },
  { label: 'whey user with request id = req_lg101 login failed', icon: '🔐' },
  { label: 'whey this request req_fp201  blocked', icon: '🚫' },
  { label: 'Why did payments fail?', icon: '💳' },
  { label: 'give me any Database issues?', icon: '🗄️' },
  { label: 'Is there any Security threats?', icon: '🛡️' },
  { label: 'list all the  Application errors?', icon: '🐛' },
  { label: 'give me any Performance problems?', icon: '⚡' },
];

function formatTimestamp(date: Date): string {
  return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function formatDuration(ms: number): string {
  if (ms < 1000) return `${ms}ms`;
  return `${(ms / 1000).toFixed(1)}s`;
}

// Session storage helpers (client-side only, never sent to server storage)
const SESSION_KEY = 'log-inspector-config';

function saveToSession(data: { platform: string; brainModel: string; embeddingModel: string; apiKey: string; ollamaHost?: string }) {
  if (typeof window !== 'undefined') {
    sessionStorage.setItem(SESSION_KEY, JSON.stringify(data));
  }
}

function loadFromSession(): { platform: string; brainModel: string; embeddingModel: string; apiKey: string; ollamaHost?: string } | null {
  if (typeof window !== 'undefined') {
    const data = sessionStorage.getItem(SESSION_KEY);
    if (data) {
      try {
        return JSON.parse(data);
      } catch {
        return null;
      }
    }
  }
  return null;
}

function clearSession() {
  if (typeof window !== 'undefined') {
    sessionStorage.removeItem(SESSION_KEY);
  }
}

export default function Playground(): JSX.Element {
  // Configuration state
  const [isConfigured, setIsConfigured] = useState(false);
  const [isInitializing, setIsInitializing] = useState(false);
  const [initProgress, setInitProgress] = useState(0);
  const [initMessage, setInitMessage] = useState('');
  const [selectedPlatform, setSelectedPlatform] = useState<string>('openai');
  const [selectedBrainModel, setSelectedBrainModel] = useState<string>('gpt-4o-mini');
  const [selectedEmbeddingModel, setSelectedEmbeddingModel] = useState<string>('text-embedding-3-small');
  const [apiKey, setApiKey] = useState<string>('');
  const [ollamaHost, setOllamaHost] = useState<string>('http://localhost:11434');
  const [showApiKey, setShowApiKey] = useState(false);
  const [logsCount, setLogsCount] = useState(0);

  // File upload state
  const [uploadedFile, setUploadedFile] = useState<File | null>(null);
  const [isUploading, setIsUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [uploadMessage, setUploadMessage] = useState('');
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Chat state
  const [messages, setMessages] = useState<Message[]>([]);
  const [sampleLogs, setSampleLogs] = useState<LogEntry[]>([]);
  const [input, setInput] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [showLogs, setShowLogs] = useState(true);
  const [sessionId] = useState(() => `session-${Date.now()}`);
  const [expandedEvidence, setExpandedEvidence] = useState<Set<string>>(new Set());
  const [apiUrl, setApiUrl] = useState<string>('http://localhost:8080');
  const [apiConnected, setApiConnected] = useState<boolean | null>(null);

  const messagesEndRef = useRef<HTMLDivElement>(null);

  const currentPlatform = PLATFORMS.find(p => p.id === selectedPlatform) || PLATFORMS[0];

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  // Load saved config and check API on mount
  useEffect(() => {
    setApiUrl(getApiUrl());

    const saved = loadFromSession();
    if (saved && (saved.apiKey || saved.ollamaHost)) {
      setSelectedPlatform(saved.platform);
      setSelectedBrainModel(saved.brainModel);
      setSelectedEmbeddingModel(saved.embeddingModel);
      setApiKey(saved.apiKey);
      if (saved.ollamaHost) {
        setOllamaHost(saved.ollamaHost);
      }
      setIsConfigured(true);
    }

    checkApiConnection();
    loadLogs();
  }, []);

  // Update models when platform changes
  useEffect(() => {
    const platform = PLATFORMS.find(p => p.id === selectedPlatform);
    if (platform) {
      if (platform.brainModels.length > 0) {
        setSelectedBrainModel(platform.brainModels[0].value);
      }
      if (platform.embeddingModels.length > 0) {
        setSelectedEmbeddingModel(platform.embeddingModels[0].value);
      }
    }
  }, [selectedPlatform]);

  const checkApiConnection = async () => {
    try {
      const response = await fetch(`${getApiUrl()}/health`);
      setApiConnected(response.ok);
    } catch {
      setApiConnected(false);
    }
  };

  const loadLogs = async () => {
    try {
      const response = await fetch(`${getApiUrl()}/logs`);
      if (response.ok) {
        const data = await response.json();
        setSampleLogs(data.logs || []);
      }
    } catch {
      setSampleLogs(getFallbackLogs());
    }
  };

  const getFallbackLogs = (): LogEntry[] => [
    { id: 'pay_001', level: 'ERROR', message: 'PaymentException: Gateway timeout for order #12345', timestamp: '2024-01-15 14:23:45', category: 'payment' },
    { id: 'pay_002', level: 'ERROR', message: 'Stripe\\Exception\\CardException: Your card was declined.', timestamp: '2024-01-15 14:24:12', category: 'payment' },
    { id: 'db_001', level: 'ERROR', message: 'Doctrine\\DBAL\\Exception: Connection timed out', timestamp: '2024-01-15 15:30:22', category: 'database' },
    { id: 'sec_001', level: 'CRITICAL', message: 'Brute force detected: 156 failed attempts from IP 192.168.1.100', timestamp: '2024-01-15 16:05:00', category: 'security' },
    { id: 'app_001', level: 'ERROR', message: 'OutOfMemoryError: Allowed memory size exhausted', timestamp: '2024-01-15 17:00:00', category: 'application' },
    { id: 'perf_001', level: 'WARNING', message: 'API response time exceeded threshold: 4.2s', timestamp: '2024-01-15 18:00:00', category: 'performance' },
  ];

  const handleFileSelect = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (file) {
      // Validate file type
      if (!file.name.endsWith('.log') && !file.name.endsWith('.txt')) {
        alert('Please select a .log or .txt file');
        return;
      }
      // Validate file size (max 10MB)
      if (file.size > 10 * 1024 * 1024) {
        alert('File size must be less than 10MB');
        return;
      }
      setUploadedFile(file);
    }
  };

  const handleUploadClick = () => {
    fileInputRef.current?.click();
  };

  const handleRemoveFile = () => {
    setUploadedFile(null);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const handleConfigure = async () => {
    if (currentPlatform.requiresApiKey && !apiKey.trim()) {
      alert('Please enter your API key');
      return;
    }

    if (!currentPlatform.requiresApiKey && !ollamaHost.trim()) {
      alert('Please enter your Ollama host URL');
      return;
    }

    setIsInitializing(true);
    setInitProgress(0);
    setInitMessage('Connecting to API...');

    // Progress animation steps
    const progressSteps = uploadedFile
      ? [
          { progress: 5, message: 'Connecting to API...' },
          { progress: 15, message: 'Uploading log file...' },
          { progress: 30, message: 'Parsing log entries...' },
          { progress: 45, message: 'Generating embeddings...' },
          { progress: 60, message: 'Indexing logs...' },
          { progress: 75, message: 'Building vector store...' },
          { progress: 90, message: 'Finalizing...' },
        ]
      : [
          { progress: 10, message: 'Connecting to API...' },
          { progress: 25, message: 'Loading sample logs...' },
          { progress: 40, message: 'Parsing payment logs...' },
          { progress: 50, message: 'Parsing database logs...' },
          { progress: 60, message: 'Parsing security logs...' },
          { progress: 70, message: 'Parsing application logs...' },
          { progress: 80, message: 'Indexing logs...' },
          { progress: 90, message: 'Finalizing...' },
        ];

    // Run animation with minimum time per step (300ms each)
    const runAnimation = async (): Promise<void> => {
      for (const step of progressSteps) {
        setInitProgress(step.progress);
        setInitMessage(step.message);
        await new Promise(resolve => setTimeout(resolve, 300));
      }
    };

    // Start animation and API call in parallel
    const animationPromise = runAnimation();

    let apiResult: any = null;
    let apiError: Error | null = null;

    const apiPromise = (async () => {
      try {
        // If file is uploaded, send it first
        if (uploadedFile) {
          const formData = new FormData();
          formData.append('file', uploadedFile);
          formData.append('session_id', sessionId);
          formData.append('platform', selectedPlatform);
          formData.append('brain_model', selectedBrainModel);
          formData.append('embedding_model', selectedEmbeddingModel);
          formData.append('api_key', apiKey.trim());
          formData.append('ollama_host', ollamaHost.trim());

          const uploadResponse = await fetch(`${apiUrl}/upload`, {
            method: 'POST',
            body: formData,
          });
          apiResult = await uploadResponse.json();
          if (apiResult.error) {
            throw new Error(apiResult.error);
          }
        } else {
          // Standard init without file upload
          const response = await fetch(`${apiUrl}/init`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              session_id: sessionId,
              platform: selectedPlatform,
              brain_model: selectedBrainModel,
              embedding_model: selectedEmbeddingModel,
              api_key: apiKey.trim(),
              ollama_host: ollamaHost.trim(),
            }),
          });
          apiResult = await response.json();
          if (apiResult.error) {
            throw new Error(apiResult.error);
          }
        }
      } catch (err) {
        apiError = err instanceof Error ? err : new Error('Failed to initialize');
      }
    })();

    // Wait for BOTH animation and API to complete
    await Promise.all([animationPromise, apiPromise]);

    // Handle result
    if (apiError) {
      setIsInitializing(false);
      setInitProgress(0);
      setInitMessage('');
      alert(`Error: ${apiError.message}. Please check your configuration.`);
      return;
    }

    // Show completion
    setInitProgress(100);
    const logCount = apiResult?.logs_count || 80;
    setInitMessage(`Ready! ${logCount} logs indexed.`);
    setLogsCount(logCount);

    // Brief pause to show 100%
    await new Promise(resolve => setTimeout(resolve, 500));

    saveToSession({
      platform: selectedPlatform,
      brainModel: selectedBrainModel,
      embeddingModel: selectedEmbeddingModel,
      apiKey: apiKey.trim(),
      ollamaHost: ollamaHost.trim(),
    });

    setIsInitializing(false);
    setIsConfigured(true);

    // Reload logs after configuration
    loadLogs();

    const uploadNote = uploadedFile ? ` from "${uploadedFile.name}"` : '';
    addSystemMessage(`🟢 Connected to ${currentPlatform.name} (Brain: ${selectedBrainModel}, Embeddings: ${selectedEmbeddingModel}). ${logCount} logs${uploadNote} loaded and ready for analysis!`);
  };

  const handleReconfigure = () => {
    clearSession();
    setIsConfigured(false);
    setApiKey('');
    setMessages([]);
    setUploadedFile(null);
  };

  const addSystemMessage = (content: string) => {
    const message: Message = {
      id: `system-${Date.now()}`,
      role: 'system',
      content,
      timestamp: new Date(),
    };
    setMessages(prev => [...prev, message]);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!input.trim() || isLoading) return;
    await sendMessage(input.trim());
    setInput('');
  };

  const sendMessage = async (question: string) => {
    if (!isConfigured) {
      addSystemMessage('Please configure your AI platform first.');
      return;
    }

    const userMessage: Message = {
      id: `user-${Date.now()}`,
      role: 'user',
      content: question,
      timestamp: new Date(),
    };

    setMessages(prev => [...prev, userMessage]);
    setIsLoading(true);

    try {
      const response = await fetch(`${apiUrl}/chat`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          session_id: sessionId,
          question: question,
          platform: selectedPlatform,
          brain_model: selectedBrainModel,
          embedding_model: selectedEmbeddingModel,
          api_key: apiKey,
          ollama_host: ollamaHost,
        }),
      });

      const data: ApiResponse = await response.json();

      if (data.error) {
        throw new Error(data.error);
      }

      const assistantMessage: Message = {
        id: `assistant-${Date.now()}`,
        role: 'assistant',
        content: data.content,
        timestamp: new Date(),
        duration: data.duration_ms,
        evidenceLogs: data.evidence_logs,
      };

      setMessages(prev => [...prev, assistantMessage]);
    } catch (error) {
      const errorMessage: Message = {
        id: `error-${Date.now()}`,
        role: 'system',
        content: `❌ Error: ${error instanceof Error ? error.message : 'Failed to get response'}. Please check your configuration and try again.`,
        timestamp: new Date(),
      };
      setMessages(prev => [...prev, errorMessage]);
    } finally {
      setIsLoading(false);
    }
  };

  const handleQuickQuestion = (question: string) => {
    if (isLoading || !isConfigured) return;
    sendMessage(question);
  };

  const resetChat = async () => {
    try {
      await fetch(`${apiUrl}/reset`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: sessionId }),
      });
    } catch {
      // Ignore reset errors
    }
    setMessages([]);
    setExpandedEvidence(new Set());
    if (isConfigured) {
      addSystemMessage('🔄 Chat cleared. Ask me anything about the logs!');
    }
  };

  const toggleEvidence = (messageId: string) => {
    setExpandedEvidence(prev => {
      const next = new Set(prev);
      if (next.has(messageId)) {
        next.delete(messageId);
      } else {
        next.add(messageId);
      }
      return next;
    });
  };

  const getLevelColor = (level: string): string => {
    switch (level.toUpperCase()) {
      case 'ERROR': return styles.logError;
      case 'CRITICAL': return styles.logCritical;
      case 'WARNING': return styles.logWarning;
      default: return styles.logInfo;
    }
  };

  const getCategoryIcon = (category?: string): string => {
    switch (category) {
      case 'payment': return '💳';
      case 'database': return '🗄️';
      case 'security': return '🛡️';
      case 'application': return '🐛';
      case 'performance': return '⚡';
      case 'laravel': return '🔴';
      case 'kubernetes': return '☸️';
      case 'microservices': return '🔗';
      default: return '📋';
    }
  };

  const renderMessageContent = (content: string) => {
    return content.split('\n').map((line, i) => {
      if (line.startsWith('## ')) {
        return <h4 key={i} className={styles.mdHeader}>{line.slice(3)}</h4>;
      }
      if (line.startsWith('**') && line.endsWith('**')) {
        return <strong key={i} className={styles.mdBold}>{line.slice(2, -2)}</strong>;
      }
      const parts = line.split(/(\*\*.*?\*\*)/g);
      return (
        <React.Fragment key={i}>
          {parts.map((part, j) => {
            if (part.startsWith('**') && part.endsWith('**')) {
              return <strong key={j}>{part.slice(2, -2)}</strong>;
            }
            return part.split(/(`[^`]+`)/g).map((codePart, k) => {
              if (codePart.startsWith('`') && codePart.endsWith('`')) {
                return <code key={k} className={styles.inlineCode}>{codePart.slice(1, -1)}</code>;
              }
              return codePart;
            });
          })}
          {i < content.split('\n').length - 1 && <br />}
        </React.Fragment>
      );
    });
  };

  // Configuration screen
  if (!isConfigured) {
    return (
      <Layout
        title="Playground"
        description="Interactive AI Log Inspector playground">
        <div className={styles.configContainer}>
          <div className={styles.configCard}>
            <div className={styles.configHeader}>
              <h1>🔍 AI Log Inspector Playground</h1>
              <p>Configure your AI platform to start analyzing logs</p>
            </div>

            <div className={styles.configSection}>
              <label className={styles.configLabel}>Select AI Platform</label>
              <div className={styles.platformGrid}>
                {PLATFORMS.map(platform => (
                  <button
                    key={platform.id}
                    className={`${styles.platformButton} ${selectedPlatform === platform.id ? styles.platformSelected : ''}`}
                    onClick={() => setSelectedPlatform(platform.id)}
                  >
                    <span className={styles.platformIcon}>{platform.icon}</span>
                    <span className={styles.platformName}>{platform.name}</span>
                  </button>
                ))}
              </div>
            </div>

            <div className={styles.configSection}>
              <label className={styles.configLabel}>Brain Model (Chat/Analysis)</label>
              <select
                value={selectedBrainModel}
                onChange={(e) => setSelectedBrainModel(e.target.value)}
                className={styles.configSelect}
              >
                {currentPlatform.brainModels.map(model => (
                  <option key={model.value} value={model.value}>
                    {model.label}
                  </option>
                ))}
              </select>
            </div>

            <div className={styles.configSection}>
              <label className={styles.configLabel}>Embedding Model (Vectorization)</label>
              <select
                value={selectedEmbeddingModel}
                onChange={(e) => setSelectedEmbeddingModel(e.target.value)}
                className={styles.configSelect}
              >
                {currentPlatform.embeddingModels.map(model => (
                  <option key={model.value} value={model.value}>
                    {model.label}
                  </option>
                ))}
              </select>
            </div>

            {currentPlatform.requiresApiKey ? (
              <div className={styles.configSection}>
                <label className={styles.configLabel}>
                  API Key
                  <a
                    href={currentPlatform.helpUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={styles.helpLink}
                  >
                    Get API Key →
                  </a>
                </label>
                <div className={styles.apiKeyInput}>
                  <input
                    type={showApiKey ? 'text' : 'password'}
                    value={apiKey}
                    onChange={(e) => setApiKey(e.target.value)}
                    placeholder={currentPlatform.placeholder}
                    className={styles.configInput}
                  />
                  <button
                    type="button"
                    onClick={() => setShowApiKey(!showApiKey)}
                    className={styles.toggleVisibility}
                  >
                    {showApiKey ? '🙈' : '👁️'}
                  </button>
                </div>
              </div>
            ) : (
              <div className={styles.configSection}>
                <label className={styles.configLabel}>
                  Ollama Host URL
                  <a
                    href={currentPlatform.helpUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={styles.helpLink}
                  >
                    Download Ollama →
                  </a>
                </label>
                <input
                  type="text"
                  value={ollamaHost}
                  onChange={(e) => setOllamaHost(e.target.value)}
                  placeholder={currentPlatform.placeholder}
                  className={styles.configInput}
                />
                <p className={styles.configHint}>
                  Make sure Ollama is running with the selected models. Run: <code>ollama pull {selectedBrainModel}</code> and <code>ollama pull {selectedEmbeddingModel}</code>
                </p>
              </div>
            )}

            {/* File Upload Section */}
            <div className={styles.configSection}>
              <label className={styles.configLabel}>
                Upload Log File (Optional)
                <span className={styles.optionalBadge}>Optional</span>
              </label>
              <div className={styles.uploadArea}>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept=".log,.txt"
                  onChange={handleFileSelect}
                  className={styles.hiddenInput}
                />
                {uploadedFile ? (
                  <div className={styles.uploadedFile}>
                    <span className={styles.fileIcon}>📄</span>
                    <div className={styles.fileInfo}>
                      <span className={styles.fileName}>{uploadedFile.name}</span>
                      <span className={styles.fileSize}>
                        {(uploadedFile.size / 1024).toFixed(1)} KB
                      </span>
                    </div>
                    <button
                      type="button"
                      onClick={handleRemoveFile}
                      className={styles.removeFileButton}
                    >
                      ✕
                    </button>
                  </div>
                ) : (
                  <button
                    type="button"
                    onClick={handleUploadClick}
                    className={styles.uploadButton}
                  >
                    <span className={styles.uploadIcon}>📁</span>
                    <span>Choose a log file (.log, .txt)</span>
                    <span className={styles.uploadHint}>or use sample logs</span>
                  </button>
                )}
              </div>
              <p className={styles.configHint}>
                Upload your own log file to analyze, or leave empty to use sample logs. Max 10MB.
              </p>
            </div>

            <div className={styles.securityNote}>
              <span className={styles.securityIcon}>🔒</span>
              <span>
                {currentPlatform.requiresApiKey
                  ? 'Your API key is stored only in your browser session and is never saved on our servers. It will be cleared when you close this tab.'
                  : 'Ollama runs locally on your machine. No data is sent to external servers.'}
              </span>
            </div>

            {isInitializing ? (
              <div className={styles.loadingSection}>
                <div className={styles.progressBar}>
                  <div
                    className={styles.progressFill}
                    style={{ width: `${initProgress}%` }}
                  />
                </div>
                <div className={styles.loadingMessage}>
                  <span className={styles.loadingSpinner}>⏳</span>
                  {initMessage}
                </div>
                {logsCount > 0 && (
                  <div className={styles.logsCountBadge}>
                    📋 {logsCount} logs indexed
                  </div>
                )}
              </div>
            ) : (
              <button
                onClick={handleConfigure}
                className={styles.startButton}
                disabled={currentPlatform.requiresApiKey ? !apiKey.trim() : !ollamaHost.trim()}
              >
                🚀 Start Analyzing Logs
              </button>
            )}

            {apiConnected === false && (
              <div className={styles.apiWarning}>
                <strong>⚠️ API Server Not Running</strong>
                <p>Start the playground API server:</p>
                <code>php -S localhost:8080 examples/playground-api.php</code>
              </div>
            )}
          </div>
        </div>
      </Layout>
    );
  }

  // Main playground UI
  return (
    <Layout
      title="Playground"
      description="Interactive AI Log Inspector playground">
      <div className={styles.playgroundContainer}>
        {/* Sidebar with sample logs */}
        <aside className={`${styles.sidebar} ${showLogs ? styles.sidebarOpen : ''}`}>
          <div className={styles.sidebarHeader}>
            <h3>📋 Logs ({sampleLogs.length})</h3>
            <button
              className={styles.toggleButton}
              onClick={() => setShowLogs(!showLogs)}
              title={showLogs ? 'Hide logs' : 'Show logs'}
            >
              {showLogs ? '◀' : '▶'}
            </button>
          </div>
          {showLogs && (
            <div className={styles.logsList}>
              {sampleLogs.map((log, index) => (
                <div key={log.id || index} className={`${styles.logEntry} ${getLevelColor(log.level)}`}>
                  <div className={styles.logHeader}>
                    <span className={styles.logCategory}>
                      {getCategoryIcon(log.category)} {log.category || 'general'}
                    </span>
                    <span className={styles.logLevel}>{log.level}</span>
                  </div>
                  <div className={styles.logMessage}>{log.message}</div>
                  <div className={styles.logMeta}>
                    <span className={styles.logId}>{log.id}</span>
                    <span className={styles.logTime}>{log.timestamp}</span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </aside>

        {/* Main chat area */}
        <main className={styles.chatArea}>
          <div className={styles.chatHeader}>
            <div className={styles.headerLeft}>
              <h1>🤖 AI Log Inspector</h1>
              <span className={styles.platformTag}>
                {currentPlatform.icon} {currentPlatform.name}
              </span>
              <span className={styles.modelTag} title="Brain Model">{selectedBrainModel}</span>
            </div>
            <div className={styles.headerActions}>
              <button onClick={resetChat} className={styles.clearButton}>
                🔄 Clear
              </button>
              <button onClick={handleReconfigure} className={styles.reconfigureButton}>
                ⚙️ Change Platform
              </button>
            </div>
          </div>

          {/* Messages */}
          <div className={styles.messagesContainer}>
            {messages.length === 0 && (
              <div className={styles.emptyState}>
                <div className={styles.emptyIcon}>🔍</div>
                <h2>Ready to Analyze Logs</h2>
                <p>Ask questions about the logs to see AI-powered log analysis in action.</p>
                <p className={styles.emptyHint}>Try: "Why did payments fail?" or "What security issues exist?"</p>
              </div>
            )}

            {messages.map(message => (
              <div
                key={message.id}
                className={`${styles.message} ${styles[message.role]}`}
              >
                <div className={styles.messageAvatar}>
                  {message.role === 'user' ? '👤' : message.role === 'system' ? 'ℹ️' : '🤖'}
                </div>
                <div className={styles.messageContent}>
                  <div className={styles.messageText}>
                    {renderMessageContent(message.content)}
                  </div>

                  {message.evidenceLogs && message.evidenceLogs.length > 0 && (
                    <div className={styles.evidenceSection}>
                      <button
                        className={styles.evidenceToggle}
                        onClick={() => toggleEvidence(message.id)}
                      >
                        📋 Evidence Logs ({message.evidenceLogs.length})
                        <span className={styles.toggleIcon}>
                          {expandedEvidence.has(message.id) ? '▼' : '▶'}
                        </span>
                      </button>

                      {expandedEvidence.has(message.id) && (
                        <div className={styles.evidenceLogs}>
                          {message.evidenceLogs.map((log, idx) => (
                            <div key={idx} className={`${styles.evidenceLog} ${getLevelColor(log.level)}`}>
                              <div className={styles.evidenceLogHeader}>
                                <span className={styles.evidenceLogId}>
                                  {getCategoryIcon(log.category)} {log.id}
                                </span>
                                <span className={`${styles.evidenceLogLevel} ${getLevelColor(log.level)}`}>
                                  {log.level}
                                </span>
                              </div>
                              <div className={styles.evidenceLogMessage}>{log.message}</div>
                              <div className={styles.evidenceLogTime}>{log.timestamp}</div>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  )}

                  <div className={styles.messageFooter}>
                    <span className={styles.messageTime}>{formatTimestamp(message.timestamp)}</span>
                    {message.duration && (
                      <span className={styles.messageDuration}>⏱️ {formatDuration(message.duration)}</span>
                    )}
                  </div>
                </div>
              </div>
            ))}

            {isLoading && (
              <div className={`${styles.message} ${styles.assistant}`}>
                <div className={styles.messageAvatar}>🤖</div>
                <div className={styles.messageContent}>
                  <div className={styles.typing}>
                    <span></span>
                    <span></span>
                    <span></span>
                  </div>
                  <div className={styles.typingText}>Analyzing logs with {currentPlatform.name}...</div>
                </div>
              </div>
            )}
            <div ref={messagesEndRef} />
          </div>

          {/* Quick questions */}
          <div className={styles.quickQuestions}>
            {QUICK_QUESTIONS.map(q => (
              <button
                key={q.label}
                onClick={() => handleQuickQuestion(q.label)}
                className={styles.quickButton}
                disabled={isLoading}
              >
                {q.icon} {q.label}
              </button>
            ))}
          </div>

          {/* Input form */}
          <form onSubmit={handleSubmit} className={styles.inputForm}>
            <input
              type="text"
              value={input}
              onChange={(e) => setInput(e.target.value)}
              placeholder="Ask about the logs... (e.g., 'Why did payments fail?')"
              className={styles.input}
              disabled={isLoading}
            />
            <button
              type="submit"
              className={styles.sendButton}
              disabled={!input.trim() || isLoading}
            >
              {isLoading ? '⏳' : '📤'} Send
            </button>
          </form>
        </main>
      </div>
    </Layout>
  );
}
