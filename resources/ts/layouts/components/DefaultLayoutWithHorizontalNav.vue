<script lang="ts" setup>
import navItems from '@/navigation/horizontal'
import { themeConfig } from '@themeConfig'

import Footer from '@/layouts/components/Footer.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import NavBarI18n from '@core/components/I18n.vue'
import { HorizontalNavLayout } from '@layouts'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
</script>

<template>
  <HorizontalNavLayout :nav-items="navItems">
    <template #navbar>
      <RouterLink
        to="/"
        class="app-logo d-flex align-center"
      >
        <VNodeRenderer :nodes="themeConfig.app.logo" />
      </RouterLink>
      <VSpacer />

      <NavBarI18n
        v-if="themeConfig.app.i18n.enable && themeConfig.app.i18n.langConfig?.length"
        :languages="themeConfig.app.i18n.langConfig"
      />
      <UserProfile />
    </template>

    <slot />

    <template #footer>
      <Footer />
    </template>
  </HorizontalNavLayout>
</template>
