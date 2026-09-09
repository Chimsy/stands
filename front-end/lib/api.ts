import { getSessionToken } from "@/lib/session";

/**
 * Base URL of the Laravel backend, including the API version prefix. The
 * front-end only ever talks to the API from the server, so this is a private
 * variable rather than a `NEXT_PUBLIC_` one.
 */
const API_BASE_URL = process.env.API_URL ?? "http://localhost:8000/api/v1";

export class ApiError extends Error {
  constructor(
    readonly status: number,
    message: string,
    readonly errors: Record<string, string[]> = {},
  ) {
    super(message);
    this.name = "ApiError";
  }

  /** First message for a field, matching how Laravel reports validation failures. */
  fieldError(field: string): string | undefined {
    return this.errors[field]?.[0];
  }
}

interface ApiRequestOptions {
  method?: "GET" | "POST";
  body?: unknown;
  query?: Record<string, string>;
  /** Pass `null` to make an unauthenticated call, or omit to use the current session. */
  token?: string | null;
}

/** Laravel API resources wrap their payload in a `data` key. */
export interface Envelope<T> {
  data: T;
}

export async function apiRequest<T>(path: string, options: ApiRequestOptions = {}): Promise<T> {
  const token = options.token !== undefined ? options.token : await getSessionToken();
  const query = options.query ? `?${new URLSearchParams(options.query)}` : "";

  const response = await fetch(`${API_BASE_URL}${path}${query}`, {
    method: options.method ?? "GET",
    headers: {
      Accept: "application/json",
      ...(options.body === undefined ? {} : { "Content-Type": "application/json" }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
    // Responses are token-scoped, so they are never shared across visitors.
    cache: "no-store",
  });

  if (response.status === 204) {
    return undefined as T;
  }

  const payload = await response.json().catch(() => null);

  if (!response.ok) {
    throw new ApiError(
      response.status,
      (payload as { message?: string } | null)?.message ?? `Request to ${path} failed.`,
      (payload as { errors?: Record<string, string[]> } | null)?.errors ?? {},
    );
  }

  return payload as T;
}
