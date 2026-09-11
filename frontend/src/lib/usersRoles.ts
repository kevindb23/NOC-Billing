import { apiRequest } from './api'

export type Paginated<T> = {
  data: T[]
  current_page: number
  last_page: number
  total: number
}

export type UserRole = { id: number; name: string }
export type User = {
  public_id: string
  name: string
  email: string
  status: string
  membership_status: string
  membership_is_default: boolean
  roles: UserRole[]
  created_at?: string | null
  updated_at?: string | null
}
export type Role = {
  id: number
  name: string
  organization_id: number | null
  scope: 'global' | 'organization'
  assignment_count: number | null
  permission_count: number | null
  permission_ids?: number[]
  permissions?: Record<string, number[]>
}
export type Permission = { id: number; name: string; action: string }
export type PermissionGroup = { group: string; permissions: Permission[] }
export type UserRequest = { name: string; email: string; password?: string; status?: 'active' | 'inactive'; role_ids?: number[] }
export type RoleRequest = { name: string; permission_ids?: number[] }

export function listUsers(token?: string, page = 1) {
  return apiRequest<{ data: Paginated<User> }>(`/users?per_page=20&page=${page}`, {}, token)
}

export function createUser(payload: UserRequest, token?: string) {
  return apiRequest<{ data: User }>('/users', { method: 'POST', body: JSON.stringify(payload) }, token)
}

export function updateUser(publicId: string, payload: Partial<UserRequest>, token?: string) {
  return apiRequest<{ data: User }>(`/users/${encodeURIComponent(publicId)}`, { method: 'PUT', body: JSON.stringify(payload) }, token)
}

export function deactivateUser(publicId: string, token?: string) {
  return apiRequest<{ data: User }>(`/users/${encodeURIComponent(publicId)}`, { method: 'DELETE' }, token)
}

export function reactivateUser(publicId: string, token?: string) {
  return updateUser(publicId, { status: 'active' }, token)
}

export function listRoles(token?: string, page = 1) {
  return apiRequest<{ data: Paginated<Role> }>(`/roles?per_page=20&page=${page}`, {}, token)
}

export function getRole(id: number, token?: string) {
  return apiRequest<{ data: Role }>(`/roles/${id}`, {}, token)
}

export function createRole(payload: RoleRequest, token?: string) {
  return apiRequest<{ data: Role }>('/roles', { method: 'POST', body: JSON.stringify(payload) }, token)
}

export function updateRole(id: number, payload: Partial<RoleRequest>, token?: string) {
  return apiRequest<{ data: Role }>(`/roles/${id}`, { method: 'PUT', body: JSON.stringify(payload) }, token)
}

export function deleteRole(id: number, token?: string) {
  return apiRequest<{ message: string }>(`/roles/${id}`, { method: 'DELETE' }, token)
}

export function listPermissions(token?: string) {
  return apiRequest<{ data: PermissionGroup[] }>('/permissions', {}, token)
}
