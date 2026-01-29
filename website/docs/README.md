# AI Log Inspector Agent - Documentation

This directory contains the complete documentation for the AI Log Inspector Agent package.

## 📖 Documentation Structure

```
docs/
├── index.md                    # Main documentation index
│
├── intro/
│   └── overview.md            # Package overview and introduction
│
├── getting-started/
│   ├── installation.md        # Installation guide
│   ├── quickstart.md          # 5-minute quick start
│   └── configuration.md       # Configuration options
│
├── core-concepts/
│   ├── architecture.md        # System architecture
│   ├── vector-stores.md       # Storage backends
│   └── semantic-search.md     # How semantic search works
│
├── tools/
│   ├── log-search-tool.md     # LogSearchTool documentation
│   ├── request-context-tool.md # RequestContextTool documentation
│   └── custom-tools.md        # Building custom tools
│
├── usage/
│   ├── basic-usage.md         # Common usage patterns
│   ├── chat-interface.md      # Conversational debugging
│   ├── log-indexing.md        # Loading and indexing logs
│   └── multi-platform.md      # Multi-platform support
│
├── advanced/
│   ├── best-practices.md      # Production best practices
│   ├── performance.md         # Performance optimization
│   ├── security.md            # Security considerations
│   └── custom-tools.md        # Advanced extensibility
│
├── api-reference/
│   ├── log-inspector-agent.md # LogInspectorAgent API
│   ├── log-inspector-chat.md  # LogInspectorChat API
│   ├── tools.md               # Tool interfaces
│   └── factories.md           # Factory classes
│
└── examples/
    ├── basic-usage.md         # Simple examples
    ├── production-setup.md    # Production configuration
    ├── laravel-integration.md # Laravel examples
    └── symfony-integration.md # Symfony examples
```

## 🚀 Quick Navigation

### For Beginners
1. Start with [Overview](intro/overview.md) to understand what the package does
2. Follow [Installation](getting-started/installation.md) to set it up
3. Try the [Quick Start](getting-started/quickstart.md) tutorial
4. Browse [Examples](examples/basic-usage.md) for more patterns

### For Developers
1. Read the [Architecture](core-concepts/architecture.md) guide
2. Learn about [Tools](tools/log-search-tool.md)
3. Check [API Reference](api-reference/log-inspector-agent.md)
4. Review [Best Practices](advanced/best-practices.md)

### For Production
1. Follow [Best Practices](advanced/best-practices.md)
2. Review [Security](advanced/security.md) considerations
3. Optimize with [Performance](advanced/performance.md) guide
4. See [Production Setup](examples/production-setup.md) example

## 📝 Documentation Format

All documentation files are written in Markdown and follow these conventions:

- **Headers**: Use ATX-style headers (`#`, `##`, `###`)
- **Code blocks**: Always specify language for syntax highlighting
- **Links**: Use relative paths within docs
- **Examples**: Include complete, runnable code examples
- **Sections**: Organize with clear headings and table of contents

## 🌐 Online Documentation

The documentation can be published as a static site using:

- **GitHub Pages**: Automatically from the `docs/` folder
- **Read the Docs**: Using MkDocs or Sphinx
- **VitePress**: For a modern, fast documentation site
- **Docusaurus**: For a full-featured documentation site

### Publishing with GitHub Pages

```bash
# Enable GitHub Pages in repository settings
# Source: docs/index.md
# URL: https://ramyhakam.github.io/ai-log-inspector-agent/
```

### Using MkDocs

```bash
# Install MkDocs
pip install mkdocs mkdocs-material

# Create mkdocs.yml
mkdocs new .

# Serve locally
mkdocs serve

# Build static site
mkdocs build
```

### Using VitePress

```bash
# Install VitePress
npm install -D vitepress

# Initialize
npx vitepress init

# Serve locally
npm run docs:dev

# Build
npm run docs:build
```

## 🤝 Contributing to Docs

### Adding New Documentation

1. Create markdown file in appropriate directory
2. Follow existing structure and formatting
3. Add links to/from `index.md`
4. Include code examples with proper syntax highlighting
5. Test all internal links

### Documentation Guidelines

- **Be Clear**: Write for developers of all skill levels
- **Be Complete**: Include all necessary context and examples
- **Be Accurate**: Test all code examples before adding
- **Be Concise**: Get to the point quickly
- **Be Helpful**: Anticipate common questions

### Example Structure

```markdown
# Page Title

Brief introduction (1-2 paragraphs).

## Section 1

Content with examples.

```php
// Code example
$agent = new LogInspectorAgent();
```

## Section 2

More content.

## Next Steps

- Link to related docs
- Link to examples
```

## 🔧 Local Preview

To preview documentation locally:

```bash
# Simple HTTP server (Python)
cd docs
python3 -m http.server 8000

# Or with PHP
php -S localhost:8000

# Then open: http://localhost:8000
```

## 📚 Additional Resources

- **GitHub Repository**: https://github.com/RamyHakam/ai-log-inspector-agent
- **Package on Packagist**: https://packagist.org/packages/hakam/ai-log-inspector-agent
- **Issue Tracker**: https://github.com/RamyHakam/ai-log-inspector-agent/issues
- **Discussions**: https://github.com/RamyHakam/ai-log-inspector-agent/discussions

## ⚖️ License

The documentation is licensed under the same MIT license as the package itself. See [LICENSE](../LICENSE) for details.

---

**Need help with the docs?** Open an issue or discussion on GitHub.

**Found an error?** Submit a pull request with corrections.

**Want to add content?** Contributions are welcome! Follow the guidelines above.
