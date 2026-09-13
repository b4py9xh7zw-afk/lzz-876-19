/** 不依赖 crypto.randomUUID 的简单 UUID v4 生成（兼容非安全上下文）。 */
export function v4() {
  const getByte = (() => {
    if (window.crypto?.getRandomValues) {
      const buf = new Uint8Array(1)
      return () => {
        window.crypto.getRandomValues(buf)
        return buf[0]
      }
    }
    return () => Math.floor(Math.random() * 256)
  })()

  const hex = []
  for (let i = 0; i < 36; i++) {
    if (i === 8 || i === 13 || i === 18 || i === 23) {
      hex[i] = '-'
    } else if (i === 14) {
      hex[i] = '4'
    } else if (i === 19) {
      hex[i] = ((getByte() & 0x3f) | 0x80).toString(16)
    } else {
      hex[i] = (getByte() & 0x0f).toString(16)
    }
  }
  return hex.join('')
}
