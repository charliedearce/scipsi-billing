<template>
  <div ref="host" class="payment-instruction-body text-sm text-g-800" />
</template>

<script setup lang="ts">
  import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { fetchPaymentPolicyImage } from '@/api/payments'

  const props = defineProps<{ content: string }>()
  const host = ref<HTMLElement>()
  const objectUrls: string[] = []

  const allowed = new Set([
    'P',
    'BR',
    'STRONG',
    'B',
    'EM',
    'I',
    'U',
    'S',
    'UL',
    'OL',
    'LI',
    'A',
    'IMG',
    'H1',
    'H2',
    'H3',
    'BLOCKQUOTE',
    'SPAN'
  ])
  const dropped = new Set([
    'SCRIPT',
    'STYLE',
    'IFRAME',
    'OBJECT',
    'EMBED',
    'SVG',
    'MATH',
    'LINK',
    'META'
  ])

  const looksLikeHtml = (value: string) => /<\/?[a-z][^>]*>/i.test(value)

  const releaseUrls = () => {
    objectUrls.splice(0).forEach((url) => URL.revokeObjectURL(url))
  }

  const sanitize = (html: string) => {
    const parsed = new DOMParser().parseFromString(html, 'text/html')
    const fragment = document.createDocumentFragment()
    const walk = (node: Node, parent: Node) => {
      node.childNodes.forEach((child) => {
        if (child.nodeType === Node.TEXT_NODE) {
          parent.appendChild(document.createTextNode(child.textContent || ''))
          return
        }
        if (child.nodeType !== Node.ELEMENT_NODE) return
        const element = child as HTMLElement
        if (dropped.has(element.tagName)) return
        if (!allowed.has(element.tagName)) {
          walk(element, parent)
          return
        }
        const copy = document.createElement(element.tagName.toLowerCase())
        if (element.tagName === 'A') {
          const href = element.getAttribute('href') || ''
          if (/^(https?:\/\/|mailto:)/i.test(href) && !/[\s\u0000-\u001f]/.test(href)) {
            copy.setAttribute('href', href)
            copy.setAttribute('rel', 'noopener noreferrer')
            copy.setAttribute('target', '_blank')
          } else {
            walk(element, parent)
            return
          }
        }
        if (element.tagName === 'IMG') {
          const match = /^payment-policy-image:(\d+)$/.exec(element.getAttribute('src') || '')
          if (!match) return
          copy.setAttribute(
            'alt',
            (element.getAttribute('alt') || 'Bank instruction').slice(0, 200)
          )
          copy.setAttribute('data-payment-policy-image', match[1])
        }
        if (element.tagName !== 'BR' && element.tagName !== 'IMG') walk(element, copy)
        parent.appendChild(copy)
      })
    }
    walk(parsed.body, fragment)
    return fragment
  }

  const render = async () => {
    if (!host.value) return
    releaseUrls()
    host.value.replaceChildren()
    host.value.classList.toggle('whitespace-pre-line', !looksLikeHtml(props.content))
    if (!looksLikeHtml(props.content)) {
      host.value.textContent = props.content
      return
    }
    host.value.appendChild(sanitize(props.content))
    const images = Array.from(
      host.value.querySelectorAll<HTMLImageElement>('img[data-payment-policy-image]')
    )
    await Promise.all(
      images.map(async (image) => {
        const id = Number(image.dataset.paymentPolicyImage)
        if (!id) return
        try {
          const blob = await fetchPaymentPolicyImage(id)
          if (!(blob instanceof Blob) || !blob.type.startsWith('image/')) {
            image.alt = 'Bank image unavailable'
            return
          }
          const url = URL.createObjectURL(blob)
          objectUrls.push(url)
          image.src = url
        } catch {
          image.alt = 'Bank image unavailable'
        }
      })
    )
  }

  onMounted(render)
  watch(() => props.content, render)
  onBeforeUnmount(releaseUrls)
</script>

<style scoped>
  .payment-instruction-body :deep(p),
  .payment-instruction-body :deep(ul),
  .payment-instruction-body :deep(ol),
  .payment-instruction-body :deep(blockquote) {
    margin: 0.35rem 0;
  }

  .payment-instruction-body :deep(a) {
    color: var(--theme-color);
    text-decoration: underline;
  }

  .payment-instruction-body :deep(img) {
    display: block;
    max-width: min(100%, 420px);
    height: auto;
    margin-top: 0.75rem;
    border: 1px solid var(--art-gray-300);
    border-radius: calc(var(--custom-radius) / 3 + 2px);
  }
</style>
