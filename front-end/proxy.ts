import { NextResponse, type NextRequest } from "next/server";

import { SESSION_COOKIE } from "@/lib/session-cookie";

const LOGIN_PATH = "/login";

/**
 * The whole site is behind the API's authentication, so anything without a
 * session cookie is sent to sign in first. This is only an optimistic check
 * that a token is present; whether it is still valid is verified against the
 * API in the authenticated layout, which is where the session really lives.
 */
export function proxy(request: NextRequest) {
  const hasSession = request.cookies.has(SESSION_COOKIE);
  const isLoginPage = request.nextUrl.pathname === LOGIN_PATH;

  if (!hasSession && !isLoginPage) {
    const signIn = new URL(LOGIN_PATH, request.url);
    signIn.searchParams.set("next", request.nextUrl.pathname + request.nextUrl.search);
    return NextResponse.redirect(signIn);
  }

  if (hasSession && isLoginPage) {
    return NextResponse.redirect(new URL("/", request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico|.*\\.(?:svg|png|jpg|jpeg|gif|webp|ico)$).*)"],
};
