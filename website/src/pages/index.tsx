import clsx from 'clsx';
import Link from '@docusaurus/Link';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import Layout from '@theme/Layout';
import Heading from '@theme/Heading';

import styles from './index.module.css';

function HomepageHeader() {
  const {siteConfig} = useDocusaurusContext();
  return (
    <header className={clsx('hero hero--primary', styles.heroBanner)}>
      <div className="container">
        <Heading as="h1" className="hero__title">
          🤖 {siteConfig.title}
        </Heading>
        <p className="hero__subtitle">{siteConfig.tagline}</p>
        <div className={styles.buttons}>
          <Link
            className="button button--secondary button--lg"
            to="/docs/getting-started/installation">
            Get Started - 5min ⏱️
          </Link>
          <Link
            className="button button--outline button--secondary button--lg"
            to="/docs/intro/overview"
            style={{marginLeft: '10px'}}>
            Learn More 📖
          </Link>
        </div>
      </div>
    </header>
  );
}

type FeatureItem = {
  title: string;
  emoji: string;
  description: JSX.Element;
};

const FeatureList: FeatureItem[] = [
  {
    title: 'Semantic Search',
    emoji: '🔍',
    description: (
      <>
        Advanced vector-based similarity search understands the <strong>meaning</strong> behind
        your queries, not just keyword matches.
      </>
    ),
  },
  {
    title: 'Conversational Debugging',
    emoji: '💬',
    description: (
      <>
        Multi-turn conversations that maintain context. Ask follow-ups and build
        a complete picture like talking to a colleague.
      </>
    ),
  },
  {
    title: 'AI-Powered Analysis',
    emoji: '🧠',
    description: (
      <>
        Get instant AI root cause analysis with evidence citations. AI explains
        <em> why</em> errors occurred, not just <em>what</em> happened.
      </>
    ),
  },
  {
    title: 'Request Tracing',
    emoji: '🔗',
    description: (
      <>
        Track complete request lifecycles across distributed systems with
        automatic correlation using request/trace/session IDs.
      </>
    ),
  },
  {
    title: 'Multi-Platform',
    emoji: '🎯',
    description: (
      <>
        Works with OpenAI, Anthropic Claude, Ollama (local), and any
        Symfony AI-compatible platform. Switch providers easily.
      </>
    ),
  },
  {
    title: 'Production Ready',
    emoji: '✅',
    description: (
      <>
        PHP 8.4+ with comprehensive testing, fallback strategies, and
        battle-tested Symfony AI components.
      </>
    ),
  },
];

function Feature({title, emoji, description}: FeatureItem) {
  return (
    <div className={clsx('col col--4')}>
      <div className="text--center">
        <span style={{fontSize: '4rem'}}>{emoji}</span>
      </div>
      <div className="text--center padding-horiz--md">
        <Heading as="h3">{title}</Heading>
        <p>{description}</p>
      </div>
    </div>
  );
}

function HomepageFeatures(): JSX.Element {
  return (
    <section className={styles.features}>
      <div className="container">
        <div className="row">
          {FeatureList.map((props, idx) => (
            <Feature key={idx} {...props} />
          ))}
        </div>
      </div>
    </section>
  );
}

export default function Home(): JSX.Element {
  const {siteConfig} = useDocusaurusContext();
  return (
    <Layout
      title={`${siteConfig.title}`}
      description="Chat with your logs using AI. Semantic search, conversational interface, and AI-powered root cause analysis for PHP applications.">
      <HomepageHeader />
      <main>
        <HomepageFeatures />
      </main>
    </Layout>
  );
}
