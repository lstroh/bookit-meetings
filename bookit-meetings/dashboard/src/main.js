import { createApp } from 'vue'
import App from './App.vue'
import router from './router/index.js'

const el = document.getElementById( 'bookit-meetings-app' )
if ( el ) {
	createApp( App ).use( router ).mount( el )
}

