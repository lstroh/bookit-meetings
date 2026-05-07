import { createRouter, createWebHashHistory } from 'vue-router'
import SettingsView from '../views/SettingsView.vue'

const router = createRouter( {
	history: createWebHashHistory(),
	routes: [
		{
			path: '/',
			component: SettingsView,
		},
		{
			path: '/:pathMatch(.*)*',
			redirect: '/',
		},
	],
} )

export default router

