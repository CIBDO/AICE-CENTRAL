import type { HorizontalNavItems } from '@layouts/types'

export default [
  {
    title: 'Tableau de bord',
    icon: { icon: 'tabler-chart-bar' },
    children: [
      {
        title: 'Régional',
        to: 'dashboards-regional',
        icon: { icon: 'tabler-chart-bar' },
      },
      {
        title: 'Central',
        to: 'dashboards-central',
        icon: { icon: 'tabler-chart-dots-3' },
      },
      {
        title: 'Exécutif',
        to: 'dashboards-executive',
        icon: { icon: 'tabler-chart-line' },
      },
    ],
  },
  {
    title: 'Données',
    icon: { icon: 'tabler-database' },
    children: [
      {
        title: 'Mandats',
        to: 'details-mandats',
        icon: { icon: 'tabler-file-invoice' },
      },
      {
        title: 'Recettes',
        to: 'details-recettes',
        icon: { icon: 'tabler-cash' },
      },
      {
        title: 'Banques',
        to: 'details-banques',
        icon: { icon: 'tabler-building-bank' },
      },
      {
        title: 'Programmes',
        to: 'details-programmes',
        icon: { icon: 'tabler-list-details' },
      },
      {
        title: 'Natures CE',
        to: 'details-natures-ce',
        icon: { icon: 'tabler-category' },
      },
    ],
  },
  {
    title: 'Administration',
    icon: { icon: 'tabler-settings' },
    children: [
      {
        title: 'Utilisateurs',
        to: 'admin-users',
        icon: { icon: 'tabler-users' },
      },
      {
        title: 'Rôles',
        to: 'admin-roles',
        icon: { icon: 'tabler-shield-lock' },
      },
      {
        title: 'Régions',
        to: 'admin-regions',
        icon: { icon: 'tabler-map-pin' },
      },
    ],
  },
] as HorizontalNavItems
