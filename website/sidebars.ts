import type {SidebarsConfig} from '@docusaurus/plugin-content-docs';

// This runs in Node.js - Don't use client-side code here (browser APIs, JSX...)

/**
 * Creating a sidebar enables you to:
 - create an ordered group of docs
 - render a sidebar for each doc of that group
 - provide next/previous navigation

 The sidebars can be generated from the filesystem, or explicitly defined here.

 Create as many sidebars as you want.
 */
const sidebars: SidebarsConfig = {
  docsSidebar: [
    {
      type: 'doc',
      id: 'index',
      label: '🏠 Home',
    },
    {
      type: 'category',
      label: '📖 Introduction',
      collapsed: false,
      items: [
        'intro/overview',
      ],
    },
    {
      type: 'category',
      label: '🚀 Getting Started',
      collapsed: false,
      items: [
        'getting-started/installation',
        'getting-started/quickstart',
        'getting-started/upgrading',
      ],
    },
    {
      type: 'category',
      label: '🔌 MCP Server',
      collapsed: false,
      items: [
        'mcp/overview',
        'mcp/clients',
        'mcp/configuration',
        'mcp/demo',
      ],
    },
    {
      type: 'category',
      label: '🧠 Core Concepts',
      collapsed: true,
      items: [
        'core-concepts/architecture',
        'core-concepts/log-processor-service',
      ],
    },
    {
      type: 'category',
      label: '🛠️ Tools',
      collapsed: true,
      items: [
        'tools/log-search-tool',
        'tools/request-context-tool',
      ],
    },
    {
      type: 'category',
      label: '💡 Examples',
      collapsed: true,
      items: [
        'examples/basic-usage',
        'examples/multi-tool-usage',
      ],
    },
  ],
};

export default sidebars;
