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
