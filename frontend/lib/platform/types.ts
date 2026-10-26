// Respuestas de /api/v1/platform (ver backend: Platform\AuthController).

export type PlatformSession = {
  admin: { id: number; name: string; email: string }
}

export type LoginChallenge = {
  status: 'two_factor_required' | 'two_factor_setup_required'
  challenge_token: string
  expires_in: number
}

export type TwoFactorSetup = {
  secret: string
  otpauth_url: string
  qr_svg: string
}

export type PlatformToken = {
  token: string
  expires_in: number
  recovery_codes?: string[]
}

export type StoreListStatus = 'active' | 'suspended' | 'deleted'

export type PlatformStore = {
  id: string
  name: string
  slug: string
  status: StoreListStatus
  owners: { name: string; email: string }[]
  products_count: number
  users_count: number
  created_at: string | null
  deleted_at: string | null
}

export type CreatedStore = PlatformStore & { temporary_password: string }
