import {themes as prismThemes} from 'prism-react-renderer';
import type {Config} from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';

// This runs in Node.js - Don't use client-side code here (browser APIs, JSX...)

const config: Config = {
  title: 'AI Log Inspector Agent',
  tagline: '🤖 Chat with your logs using AI - Transform debugging from tedious to effortless',
  favicon: 'img/favicon.ico',

  // Future flags, see https://docusaurus.io/docs/api/docusaurus-config#future
  future: {
    v4: true, // Improve compatibility with the upcoming Docusaurus v4
  },

  // Set the production url of your site here
  url: 'https://ramyhakam.github.io',
  // Set the /<baseUrl>/ pathname under which your site is served
  // For GitHub pages deployment, it is often '/<projectName>/'
  baseUrl: '/ai-log-inspector-agent/',

  // GitHub pages deployment config.
  // If you aren't using GitHub pages, you don't need these.
  organizationName: 'RamyHakam', // Usually your GitHub org/user name.
  projectName: 'ai-log-inspector-agent', // Usually your repo name.

  onBrokenLinks: 'warn', // Temporarily set to warn while building docs

  // Even if you don't use internationalization, you can use this field to set
  // useful metadata like html lang. For example, if your site is Chinese, you
  // may want to replace "en" with "zh-Hans".
  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      {
        docs: {
          sidebarPath: './sidebars.ts',
          routeBasePath: 'docs',
          // Please change this to your repo.
          // Remove this to remove the "edit this page" links.
          editUrl:
            'https://github.com/RamyHakam/ai-log-inspector-agent/tree/main/website/',
        },
        blog: false, // Disable blog for now
        theme: {
          customCss: './src/css/custom.css',
        },
      } satisfies Preset.Options,
    ],
  ],

  themeConfig: {
    // Replace with your project's social card
    image: 'img/docusaurus-social-card.jpg',
    colorMode: {
      respectPrefersColorScheme: true,
    },
    navbar: {
      title: 'AI Log Inspector',
      logo: {
        alt: 'AI Log Inspector Logo',
        src: 'img/logo.svg',
      },
      items: [
        {
          type: 'docSidebar',
          sidebarId: 'docsSidebar',
          position: 'left',
          label: 'Documentation',
        },
        {
          type: 'docSidebar',
          sidebarId: 'docsSidebar',
          position: 'left',
          label: 'Examples',
          to: '/docs/examples/basic-usage',
        },
        {
          type: 'docsVersionDropdown',
          position: 'right',
          dropdownActiveClassDisabled: true,
        },
        {
          href: 'https://github.com/RamyHakam/ai-log-inspector-agent',
          label: 'GitHub',
          position: 'right',
        },
        {
          href: 'https://packagist.org/packages/hakam/ai-log-inspector-agent',
          label: 'Packagist',
          position: 'right',
        },
      ],
    },
    footer: {
      style: 'dark',
      links: [
        {
          title: 'Documentation',
          items: [
            {
              label: 'Getting Started',
              to: '/docs/getting-started/installation',
            },
            {
              label: 'Quick Start',
              to: '/docs/getting-started/quickstart',
            },
            {
              label: 'Architecture',
              to: '/docs/core-concepts/architecture',
            },
          ],
        },
        {
          title: 'Resources',
          items: [
            {
              label: 'Examples',
              to: '/docs/examples/basic-usage',
            },
            {
              label: 'API Reference',
              to: '/docs/api-reference/log-inspector-agent',
            },
            {
              label: 'Best Practices',
              to: '/docs/advanced/best-practices',
            },
          ],
        },
        {
          title: 'Community',
          items: [
            {
              label: 'GitHub',
              href: 'https://github.com/RamyHakam/ai-log-inspector-agent',
            },
            {
              label: 'Issues',
              href: 'https://github.com/RamyHakam/ai-log-inspector-agent/issues',
            },
            {
              label: 'Discussions',
              href: 'https://github.com/RamyHakam/ai-log-inspector-agent/discussions',
            },
          ],
        },
        {
          title: 'More',
          items: [
            {
              label: 'Packagist',
              href: 'https://packagist.org/packages/hakam/ai-log-inspector-agent',
            },
            {
              label: 'License (MIT)',
              href: 'https://github.com/RamyHakam/ai-log-inspector-agent/blob/main/LICENSE',
            },
          ],
        },
      ],
      copyright: `Copyright © ${new Date().getFullYear()} Ramy Hakam. Built with Docusaurus. 🇵🇸 Free Palestine`,
    },
    prism: {
      theme: prismThemes.github,
      darkTheme: prismThemes.dracula,
      additionalLanguages: ['php', 'bash', 'json'],
    },
  } satisfies Preset.ThemeConfig,
};

export default config;
