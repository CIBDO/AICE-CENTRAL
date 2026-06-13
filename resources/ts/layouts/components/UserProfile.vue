<script setup lang="ts">
const router = useRouter()
const ability = useAbility()

const userData = useCookie<{ fullName?: string; login?: string; role?: string } | null>('userData')

const logout = async () => {
  try {
    await $api('/v1/auth/logout', { method: 'POST' })
  }
  catch {
    // Clear local session even if API fails
  }

  useCookie('accessToken').value = null
  userData.value = null
  useCookie('userAbilityRules').value = null
  ability.update([])

  await router.push('/login')
}
</script>

<template>
  <VBadge
    v-if="userData"
    dot
    bordered
    location="bottom right"
    offset-x="1"
    offset-y="2"
    color="success"
  >
    <VAvatar
      size="38"
      class="cursor-pointer"
      color="primary"
      variant="tonal"
    >
      <VIcon icon="tabler-user" />

      <VMenu
        activator="parent"
        width="220"
        location="bottom end"
        offset="12px"
      >
        <VList>
          <VListItem>
            <VListItemTitle class="font-weight-medium">
              {{ userData.fullName || userData.login }}
            </VListItemTitle>
            <VListItemSubtitle>
              {{ userData.role }}
            </VListItemSubtitle>
          </VListItem>

          <VDivider class="my-2" />

          <div class="px-4 py-2">
            <VBtn
              block
              size="small"
              variant="outlined"
              append-icon="tabler-logout"
              @click="logout"
            >
              Déconnexion
            </VBtn>
          </div>
        </VList>
      </VMenu>
    </VAvatar>
  </VBadge>
</template>
