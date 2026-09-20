import request from '@/utils/http'

export interface LoginParams {
  userName?: string
  email?: string
  password?: string
  account?: string
}

export interface LoginResult {
  token: string
  refreshToken?: string
  user: {
    id: number
    name: string
    email: string
    status: string
    roles: string[]
    permissions: string[]
  }
}

export function fetchLogin(params: LoginParams) {
  return request.post<LoginResult>({
    url: '/api/v1/auth/login',
    data: {
      email: params.email || params.userName,
      password: params.password
    }
  })
}

export function fetchGetUserInfo() {
  return request.get<any>({
    url: '/api/v1/auth/me'
  })
}

export function fetchLogout() {
  return request.post<{ message: string }>({
    url: '/api/v1/auth/logout'
  })
}

export function fetchRevokeSessions(userId?: number) {
  return request.post<{ message: string }>({
    url: '/api/v1/auth/revoke-sessions',
    data: userId ? { user_id: userId } : {}
  })
}
