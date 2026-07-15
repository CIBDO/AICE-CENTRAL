import { breakpointsVuetifyV3 } from '@vueuse/core'
import { VIcon } from 'vuetify/components/VIcon'
import { defineThemeConfig } from '@core'
import { Skins } from '@core/enums'

import { AppContentLayoutNav, ContentWidth, FooterType, NavbarType } from '@layouts/enums'

export const { themeConfig, layoutConfig } = defineThemeConfig({
  app: {
    title: 'DGTCP' as Lowercase<string>,
    logo: h('img', {
      src: '/images/dgtcp-logo.png',
      alt: 'DGTCP — Direction Générale du Trésor et de la Comptabilité Publique',
      class: 'dgtcp-logo__img',
    }),
    contentWidth: ContentWidth.Fluid,
    contentLayoutNav: AppContentLayoutNav.Horizontal,
    overlayNavFromBreakpoint: breakpointsVuetifyV3.lg - 1,
    i18n: {
      enable: true,
      defaultLocale: 'fr',
      langConfig: [
        {
          label: 'Français',
          i18nLang: 'fr',
          isRTL: false,
        },
        {
          label: 'English',
          i18nLang: 'en',
          isRTL: false,
        },
      ],
    },
    theme: 'light',
    skin: Skins.Bordered,
    iconRenderer: VIcon,
  },
  navbar: {
    type: NavbarType.Sticky,
    navbarBlur: false,
  },
  footer: { type: FooterType.Hidden },
  verticalNav: {
    isVerticalNavCollapsed: false,
    defaultNavItemIconProps: { icon: 'tabler-point' },
    isVerticalNavSemiDark: false,
  },
  horizontalNav: {
    type: 'sticky',
    transition: 'slide-y-reverse-transition',
    popoverOffset: 6,
  },
  icons: {
    chevronDown: { icon: 'tabler-chevron-down' },
    chevronRight: { icon: 'tabler-chevron-right', size: 18 },
    close: { icon: 'tabler-x', size: 18 },
    verticalNavPinned: { icon: 'tabler-circle-dot', size: 18 },
    verticalNavUnPinned: { icon: 'tabler-circle', size: 18 },
    sectionTitlePlaceholder: { icon: 'tabler-minus' },
  },
})
