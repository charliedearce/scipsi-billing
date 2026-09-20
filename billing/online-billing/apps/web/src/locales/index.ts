/**
 * 国际化配置
 *
 * 基于 vue-i18n 的英文界面消息目录。
 * The application intentionally exposes one locale only: English.
 *
 * ## 主要功能
 *
 * - English-only messages for a stable billing UI contract
 * - 全局注入 - 在任何组件中都可以使用 $t 函数进行翻译
 * - 类型安全 - 提供 TypeScript 类型支持
 *
 * @module locales
 * @author Art Design Pro Team
 */

import { createI18n } from 'vue-i18n'
import type { I18n, I18nOptions } from 'vue-i18n'
import { LanguageEnum } from '@/enums/appEnum'

import enMessages from './langs/en.json'

/**
 * English-only message object.
 */
const messages = {
  [LanguageEnum.EN]: enMessages
}

/**
 * i18n 配置选项
 */
const i18nOptions: I18nOptions = {
  locale: LanguageEnum.EN,
  legacy: false,
  globalInjection: true,
  fallbackLocale: LanguageEnum.EN,
  messages
}

/**
 * i18n 实例
 */
const i18n: I18n = createI18n(i18nOptions)

/**
 * 翻译函数类型
 */
interface Translation {
  (key: string): string
}

/**
 * 全局翻译函数
 * 可在任何地方使用，无需导入 useI18n
 */
export const $t = i18n.global.t as Translation

export default i18n
