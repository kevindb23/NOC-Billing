// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { PermissionMatrix } from './PermissionMatrix'
import { RolesPage } from './RolesPage'
import { UsersPage } from './UsersPage'
import { UserForm } from './UserForm'
import { RoleForm } from './RoleForm'
import App from '@/App'

vi.mock('@/components/DashboardPage', () => ({ DashboardPage: () => null }))

vi.mock('@/lib/usersRoles', () => ({
  listUsers: vi.fn(),
  createUser: vi.fn(),
  updateUser: vi.fn(),
  deactivateUser: vi.fn(),
  reactivateUser: vi.fn(),
  listRoles: vi.fn(),
  listPermissions: vi.fn(),
  getRole: vi.fn(),
  hasPermission: (permissions: string[] | undefined, permission: string) => permissions === undefined || permissions.includes(permission),
  createRole: vi.fn(),
  updateRole: vi.fn(),
  deleteRole: vi.fn(),
}))

const mockedUsers = vi.mocked(await import('@/lib/usersRoles'))
const actualUsersRoles = await vi.importActual<typeof import('@/lib/usersRoles')>('@/lib/usersRoles')

describe('users and roles administration', () => {
  beforeEach(() => {
    cleanup()
    vi.clearAllMocks()
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: [] }) }))
  })

  it('selects the users API path with pagination', async () => {
    await actualUsersRoles.listUsers('token', 2)
    expect(fetch).toHaveBeenCalledWith('/api/v1/users?per_page=20&page=2', expect.anything())
  })

  it('includes permission ids in permission responses and sends mutation payloads', async () => {
    vi.stubGlobal('fetch', vi.fn()
      .mockResolvedValueOnce({ ok: true, json: async () => ({ data: [{ group: 'System', permissions: [{ id: 7, name: 'users.view', action: 'view' }] }] }) })
      .mockResolvedValue({ ok: true, json: async () => ({ data: {} }) }))
    const permissions = await actualUsersRoles.listPermissions('token')
    await actualUsersRoles.reactivateUser('usr_1', 'token')
    expect(permissions.data[0].permissions[0].id).toBe(7)
    expect(fetch).toHaveBeenLastCalledWith('/api/v1/users/usr_1', expect.objectContaining({ method: 'PUT', body: JSON.stringify({ status: 'active' }) }))
  })

  it('hydrates the user edit form whenever the record changes', () => {
    const user = { public_id: 'usr_1', name: 'Ada', email: 'ada@example.com', status: 'active', membership_status: 'active', membership_is_default: false, roles: [{ id: 1, name: 'Admin' }] }
    const nextUser = { ...user, public_id: 'usr_2', name: 'Grace', email: 'grace@example.com', membership_status: 'inactive', roles: [] }
    const view = render(<UserForm open mode="edit" user={user} roles={[{ id: 1, name: 'Admin', organization_id: 1, scope: 'organization', assignment_count: 0, permission_count: 0 }]} onClose={vi.fn()} onSubmit={vi.fn()} />)
    expect((screen.getByLabelText('Name') as HTMLInputElement).value).toBe('Ada')
    view.rerender(<UserForm open mode="edit" user={nextUser} roles={[]} onClose={vi.fn()} onSubmit={vi.fn()} />)
    expect((screen.getByLabelText('Name') as HTMLInputElement).value).toBe('Grace')
    expect((screen.getByLabelText('Status') as HTMLSelectElement).value).toBe('inactive')
  })

  it('hydrates the role edit form whenever the record changes and keeps global roles read-only', () => {
    const role = { id: 1, name: 'Support', organization_id: 1, scope: 'organization' as const, assignment_count: 0, permission_count: 1, permission_ids: [7] }
    const nextRole = { ...role, id: 2, name: 'Billing', permission_ids: [] }
    const groups = [{ group: 'System', permissions: [{ id: 7, name: 'users.view', action: 'view' }] }]
    const view = render(<RoleForm open mode="edit" role={role} groups={groups} onClose={vi.fn()} onSubmit={vi.fn()} />)
    expect((screen.getByLabelText('Role name') as HTMLInputElement).value).toBe('Support')
    expect((screen.getByLabelText('users.view') as HTMLInputElement).checked).toBe(true)
    view.rerender(<RoleForm open mode="edit" role={nextRole} groups={groups} onClose={vi.fn()} onSubmit={vi.fn()} />)
    expect((screen.getByLabelText('Role name') as HTMLInputElement).value).toBe('Billing')
    expect((screen.getByLabelText('users.view') as HTMLInputElement).checked).toBe(false)
  })

  it('sends create and edit endpoint payloads for users and roles', async () => {
    const request = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: {} }) })
    vi.stubGlobal('fetch', request)
    await actualUsersRoles.createUser({ name: 'Ada', email: 'ada@example.com', password: 'secret123', role_ids: [3] }, 'token')
    await actualUsersRoles.updateUser('usr_1', { name: 'Grace', role_ids: [4] }, 'token')
    await actualUsersRoles.createRole({ name: 'Support', permission_ids: [7] }, 'token')
    await actualUsersRoles.updateRole(2, { name: 'Billing', permission_ids: [] }, 'token')
    expect(request.mock.calls.map(call => [call[0], call[1].method, JSON.parse(call[1].body)])).toEqual([
      ['/api/v1/users', 'POST', { name: 'Ada', email: 'ada@example.com', password: 'secret123', role_ids: [3] }],
      ['/api/v1/users/usr_1', 'PUT', { name: 'Grace', role_ids: [4] }],
      ['/api/v1/roles', 'POST', { name: 'Support', permission_ids: [7] }],
      ['/api/v1/roles/2', 'PUT', { name: 'Billing', permission_ids: [] }],
    ])
  })

  it('renders users and never displays a stored password', async () => {
    mockedUsers.listUsers.mockResolvedValue({ data: { data: [{ public_id: 'usr_1', name: 'Ada Lovelace', email: 'ada@example.com', status: 'active', membership_status: 'active', membership_is_default: false, roles: [{ id: 1, name: 'Administrator' }] }, { public_id: 'usr_2', name: 'Inactive User', email: 'inactive@example.com', status: 'active', membership_status: 'inactive', membership_is_default: false, roles: [] }], current_page: 1, last_page: 1, total: 2 } })
    mockedUsers.listRoles.mockResolvedValue({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
    render(<UsersPage token="token" permissions={['users.view', 'users.update', 'users.delete']} />)

    expect(await screen.findByText('Ada Lovelace')).toBeTruthy()
    expect(screen.getByRole('button', { name: /reactivate/i })).toBeTruthy()
    expect(screen.queryByText(/password/i)).toBeNull()
  })

  it('renders user empty and error states', async () => {
    mockedUsers.listUsers.mockResolvedValueOnce({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
    const { unmount } = render(<UsersPage token="token" />)
    expect(await screen.findByText('No users found.')).toBeTruthy()
    unmount()
    mockedUsers.listUsers.mockRejectedValueOnce(new Error('Users unavailable'))
    render(<UsersPage token="token" />)
    expect(await screen.findByText('Users unavailable')).toBeTruthy()
  })

  it('renders users for users.view-only operators without loading role options', async () => {
    mockedUsers.listUsers.mockResolvedValue({ data: { data: [{ public_id: 'usr_view', name: 'View Only', email: 'view@example.com', status: 'active', membership_status: 'active', membership_is_default: false, roles: [] }], current_page: 1, last_page: 1, total: 1 } })
    render(<UsersPage token="token" permissions={['users.view']} />)

    expect(await screen.findByText('View Only')).toBeTruthy()
    expect(mockedUsers.listRoles).not.toHaveBeenCalled()
  })

  it('toggles permission groups through controlled checkbox changes', () => {
    const onChange = vi.fn()
    render(<PermissionMatrix groups={[{ group: 'System', permissions: [{ id: 1, name: 'users.view', action: 'view' }, { id: 2, name: 'users.update', action: 'update' }] }]} selectedIds={[1]} onChange={onChange} />)
    fireEvent.click(screen.getByLabelText('users.update'))
    expect(onChange).toHaveBeenCalledWith([1, 2])
    fireEvent.click(screen.getByLabelText('users.view'))
    expect(onChange).toHaveBeenLastCalledWith([])
  })

  it('loads roles and permissions and exposes role creation', async () => {
    mockedUsers.listRoles.mockResolvedValue({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
    mockedUsers.listPermissions.mockResolvedValue({ data: [{ group: 'System', permissions: [{ id: 1, name: 'users.view', action: 'view' }] }] })
    render(<RolesPage token="token" />)
    expect(await screen.findByText('No roles found.')).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: /new role/i }))
    expect(screen.getByRole('dialog')).toBeTruthy()
    expect(screen.getByLabelText('Role name')).toBeTruthy()
  })

  it('does not expose edit for global roles', async () => {
    mockedUsers.listRoles.mockResolvedValue({ data: { data: [{ id: 1, name: 'Global admin', organization_id: null, scope: 'global', assignment_count: 0, permission_count: 1 }], current_page: 1, last_page: 1, total: 1 } })
    mockedUsers.listPermissions.mockResolvedValue({ data: [] })
    render(<RolesPage token="token" permissions={['roles.view', 'roles.update', 'roles.delete']} />)
    expect(await screen.findByText('Global admin')).toBeTruthy()
    expect(screen.queryByRole('button', { name: 'Edit Global admin' })).toBeNull()
    expect(screen.queryByRole('button', { name: 'Delete Global admin' })).toBeNull()
  })

  it('hides administration navigation and actions without permissions', async () => {
    localStorage.setItem('isp-session', JSON.stringify({ token: 'token', user: { name: 'Viewer', email: 'viewer@example.com' }, permissions: [] }))
    const app = render(<App />)
    expect(screen.queryByRole('button', { name: 'Users' })).toBeNull()
    expect(screen.queryByRole('button', { name: 'Roles' })).toBeNull()

    mockedUsers.listUsers.mockResolvedValue({ data: { data: [{ public_id: 'usr_2', name: 'Inactive User', email: 'inactive@example.com', status: 'active', membership_status: 'inactive', membership_is_default: false, roles: [] }], current_page: 1, last_page: 1, total: 1 } })
    app.unmount()
    render(<UsersPage token="token" permissions={['users.view']} />)
    expect(await screen.findByText('Inactive User')).toBeTruthy()
    expect(screen.queryByRole('button', { name: /new user/i })).toBeNull()
    expect(screen.queryByRole('button', { name: /reactivate inactive user/i })).toBeNull()
  })
})
