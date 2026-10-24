// Laravel sets this cookie on every response and accepts it back as a CSRF header.
export const xsrfToken = () => decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');
