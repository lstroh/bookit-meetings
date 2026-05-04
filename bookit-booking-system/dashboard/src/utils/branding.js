const DEFAULT_BRANDING = {
  logoUrl: '',
  primaryColour: '#4F46E5',
  businessName: '',
  poweredByVisible: true
}

const HEX_PATTERN = /^#[0-9A-Fa-f]{6}$/

const clamp = (value, min, max) => Math.min(Math.max(value, min), max)

const hexToRgb = (hex) => {
  const normalized = hex.replace('#', '')
  return {
    r: parseInt(normalized.slice(0, 2), 16),
    g: parseInt(normalized.slice(2, 4), 16),
    b: parseInt(normalized.slice(4, 6), 16)
  }
}

const rgbToHex = ({ r, g, b }) => {
  const toHex = (channel) => clamp(channel, 0, 255).toString(16).padStart(2, '0')
  return `#${toHex(r)}${toHex(g)}${toHex(b)}`.toUpperCase()
}

const mixWithWhite = (rgb, amount) => ({
  r: Math.round(rgb.r + (255 - rgb.r) * amount),
  g: Math.round(rgb.g + (255 - rgb.g) * amount),
  b: Math.round(rgb.b + (255 - rgb.b) * amount)
})

const mixWithBlack = (rgb, amount) => ({
  r: Math.round(rgb.r * (1 - amount)),
  g: Math.round(rgb.g * (1 - amount)),
  b: Math.round(rgb.b * (1 - amount))
})

const buildPrimaryScale = (hex) => {
  const base = hexToRgb(hex)
  return {
    50: rgbToHex(mixWithWhite(base, 0.92)),
    100: rgbToHex(mixWithWhite(base, 0.84)),
    500: hex.toUpperCase(),
    600: rgbToHex(mixWithBlack(base, 0.12)),
    700: rgbToHex(mixWithBlack(base, 0.24))
  }
}

export const normalizeBranding = (input = {}) => {
  const normalized = { ...DEFAULT_BRANDING }

  if (typeof input.logoUrl === 'string') {
    normalized.logoUrl = input.logoUrl.trim()
  }

  if (typeof input.primaryColour === 'string' && HEX_PATTERN.test(input.primaryColour.trim())) {
    normalized.primaryColour = input.primaryColour.trim().toUpperCase()
  }

  if (typeof input.businessName === 'string') {
    normalized.businessName = input.businessName.trim().slice(0, 100)
  }

  if (typeof input.poweredByVisible === 'boolean') {
    normalized.poweredByVisible = input.poweredByVisible
  }

  return normalized
}

export const applyBranding = (brandingInput = {}) => {
  const branding = normalizeBranding(brandingInput)
  const scale = buildPrimaryScale(branding.primaryColour)

  document.documentElement.style.setProperty('--bookit-primary', branding.primaryColour)
  document.documentElement.style.setProperty('--bookit-primary-50', scale[50])
  document.documentElement.style.setProperty('--bookit-primary-100', scale[100])
  document.documentElement.style.setProperty('--bookit-primary-500', scale[500])
  document.documentElement.style.setProperty('--bookit-primary-600', scale[600])
  document.documentElement.style.setProperty('--bookit-primary-700', scale[700])

  return branding
}

export const getDefaultBranding = () => ({ ...DEFAULT_BRANDING })
