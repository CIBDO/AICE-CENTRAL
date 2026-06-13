import type { VerticalNavItems } from '@layouts/types'

export default [
  {
    heading: 'Tableau de bord',
  },
  {
    title: 'Régional',
    icon: { icon: 'tabler-chart-bar' },
    to: 'dashboards-regional',
  },
  {
    title: 'Central',
    icon: { icon: 'tabler-chart-dots-3' },
    to: 'dashboards-central',
  },
  {
    title: 'Exécutif',
    icon: { icon: 'tabler-chart-line' },
    to: 'dashboards-executive',
  },
  {
    heading: 'Données',
  },
  {
    title: 'Mandats',
    icon: { icon: 'tabler-file-invoice' },
    to: 'details-mandats',
  },
  {
    title: 'Recettes',
    icon: { icon: 'tabler-cash' },
    to: 'details-recettes',
  },
  {
    title: 'Banques',
    icon: { icon: 'tabler-building-bank' },
    to: 'details-banques',
  },
  {
    title: 'Programmes',
    icon: { icon: 'tabler-list-details' },
    to: 'details-programmes',
  },
  {
    title: 'Natures CE',
    icon: { icon: 'tabler-category' },
    to: 'details-natures-ce',
  },
  {
    heading: 'Administration',
  },
  {
    title: 'Utilisateurs',
    icon: { icon: 'tabler-users' },
    to: 'admin-users',
  },
  {
    title: 'Rôles',
    icon: { icon: 'tabler-shield-lock' },
    to: 'admin-roles',
  },
  {
    title: 'Régions',
    icon: { icon: 'tabler-map-pin' },
    to: 'admin-regions',
  },
] as VerticalNavItems
