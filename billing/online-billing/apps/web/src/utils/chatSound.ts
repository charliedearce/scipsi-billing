/**
 * Short UI tones for chat send / receive (no external audio assets).
 */

type ChatTone = 'send' | 'receive'

let audioCtx: AudioContext | null = null
let unlocked = false

function getCtx(): AudioContext | null {
  if (typeof window === 'undefined') return null
  const AC = window.AudioContext || (window as any).webkitAudioContext
  if (!AC) return null
  if (!audioCtx) audioCtx = new AC()
  return audioCtx
}

/** Call from a user gesture so browsers allow AudioContext playback. */
export function unlockChatSounds(): void {
  const ctx = getCtx()
  if (!ctx) return
  if (ctx.state === 'suspended') {
    ctx.resume().catch(() => {})
  }
  unlocked = true
}

function beep(freq: number, durationMs: number, gain = 0.04, type: OscillatorType = 'sine'): void {
  const ctx = getCtx()
  if (!ctx || !unlocked) return
  if (ctx.state === 'suspended') {
    ctx.resume().catch(() => {})
  }

  const now = ctx.currentTime
  const osc = ctx.createOscillator()
  const g = ctx.createGain()
  osc.type = type
  osc.frequency.value = freq
  g.gain.setValueAtTime(0.0001, now)
  g.gain.exponentialRampToValueAtTime(gain, now + 0.01)
  g.gain.exponentialRampToValueAtTime(0.0001, now + durationMs / 1000)
  osc.connect(g)
  g.connect(ctx.destination)
  osc.start(now)
  osc.stop(now + durationMs / 1000 + 0.02)
}

export function playChatSound(tone: ChatTone): void {
  try {
    unlockChatSounds()
    if (tone === 'send') {
      beep(880, 70, 0.035, 'triangle')
      setTimeout(() => beep(1175, 55, 0.028, 'triangle'), 55)
    } else {
      beep(660, 90, 0.04, 'sine')
      setTimeout(() => beep(520, 110, 0.03, 'sine'), 70)
    }
  } catch {
    // Non-fatal: sound is a convenience only
  }
}
