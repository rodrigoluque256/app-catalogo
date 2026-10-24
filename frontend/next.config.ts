import type { NextConfig } from 'next'

// Hosts públicos de las imágenes (R2 / CDN), separados por coma. Ej: IMAGE_HOSTS=cdn.midominio.com
const imageHosts = (process.env.IMAGE_HOSTS ?? '').split(',').map((host) => host.trim()).filter(Boolean)

// Solo desarrollo: hosts extra desde los que se abre el dev server (ej. otro contenedor). Ver .env.example.
const devOrigins = (process.env.DEV_ORIGINS ?? '').split(',').map((host) => host.trim()).filter(Boolean)

const nextConfig: NextConfig = {
  // Permite compilar (npm run build) sin pisar el .next del servidor de desarrollo que esté corriendo.
  distDir: process.env.NEXT_DIST_DIR ?? '.next',
  allowedDevOrigins: devOrigins,
  output: 'standalone',
  images: {
    remotePatterns: imageHosts.map((hostname) => ({ protocol: 'https' as const, hostname })),
  },
}

export default nextConfig
