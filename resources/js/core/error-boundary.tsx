import { Component, ErrorInfo, ReactNode } from 'react';

type ErrorBoundaryProps = {
  children: ReactNode;
  title?: string;
};

type ErrorBoundaryState = {
  hasError: boolean;
  errorId: string | null;
};

export class AppErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  state: ErrorBoundaryState = { hasError: false, errorId: null };

  static getDerivedStateFromError(): ErrorBoundaryState {
    return { hasError: true, errorId: crypto.randomUUID() };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    const errorId = this.state.errorId ?? 'unknown';
    console.error('[TOEFL House] unhandled frontend render error', { errorId, error, componentStack: info.componentStack });
  }

  private recover = (): void => {
    this.setState({ hasError: false, errorId: null });
  };

  render(): ReactNode {
    if (!this.state.hasError) return this.props.children;

    return (
      <main className="error-boundary" role="alert" aria-labelledby="error-boundary-title">
        <div className="panel error-boundary-card">
          <p className="eyebrow">Application recovery</p>
          <h1 id="error-boundary-title">This workspace could not be rendered.</h1>
          <p className="lede">The server-side record and authorization boundaries are unchanged. The interface hit an unexpected client error and was stopped safely instead of showing a broken partial state.</p>
          <div className="error-boundary-actions">
            <button className="button" type="button" onClick={this.recover}>Try again</button>
            <button className="button secondary" type="button" onClick={() => window.location.reload()}>Reload page</button>
            <a className="button secondary" href="/workspace">Return to Home</a>
          </div>
          {this.state.errorId && <small className="muted">Reference: {this.state.errorId}</small>}
        </div>
      </main>
    );
  }
}
