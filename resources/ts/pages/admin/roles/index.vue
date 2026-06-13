<script setup lang="ts">
import DashboardHero from '@/components/aice/DashboardHero.vue'
import { useAdminRoles } from '@/composables/useAdminRoles'

definePage({ meta: { layout: 'default' } })

const { loading, roles, fetch } = useAdminRoles()

onMounted(() => fetch())
</script>

<template>
  <div class="aice-page">
    <DashboardHero
      title="Rôles et permissions"
      subtitle="Matrice des droits d'accès au hub central — lecture seule en v1."
      :stats="[{ label: 'Rôles', value: String(roles.length) }]"
    />

    <VRow>
      <VCol
        v-for="role in roles"
        :key="role.id"
        cols="12"
        sm="6"
        lg="4"
      >
        <VCard
          class="aice-quick-card"
          elevation="0"
          style="cursor: default;"
        >
          <VCardText class="pa-5">
            <div class="d-flex align-center gap-3 mb-3">
              <div class="aice-quick-card__icon">
                <VIcon
                  icon="tabler-shield"
                  size="22"
                />
              </div>
              <div>
                <div class="text-subtitle-1 font-weight-bold">
                  {{ role.nom }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ role.permissions_count }} permission(s)
                </div>
              </div>
            </div>
            <p
              v-if="role.description"
              class="text-body-2 text-medium-emphasis mb-0"
            >
              {{ role.description }}
            </p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VSkeletonLoader
      v-if="loading && !roles.length"
      type="card@3"
    />
  </div>
</template>
