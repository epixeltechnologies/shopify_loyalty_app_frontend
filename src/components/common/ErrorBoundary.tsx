import { Component, type ErrorInfo, type PropsWithChildren, type ReactNode } from 'react';
import { Banner, Page } from '@shopify/polaris';

interface State {
  error: Error | null;
}

/**
 * Top-level render-error safety net. This does NOT replace the
 * apiClient response interceptor (which handles request/response
 * errors via notifications) — it only catches errors thrown during
 * React's render phase, which a toast can't represent since the tree
 * that would show it may itself be broken.
 */
export class ErrorBoundary extends Component<PropsWithChildren<{ fallback?: ReactNode }>, State> {
  state: State = { error: null };

  static getDerivedStateFromError(error: Error): State {
    return { error };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    console.error('Unhandled render error:', error, info.componentStack);
  }

  render() {
    if (this.state.error) {
      return (
        this.props.fallback ?? (
          <Page title="Something went wrong">
            <Banner tone="critical" title="This page couldn't be displayed">
              Please refresh the page. If the problem continues, contact support.
            </Banner>
          </Page>
        )
      );
    }

    return this.props.children;
  }
}
