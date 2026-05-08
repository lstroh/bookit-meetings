import { createApp } from 'vue'
import App from './App.vue'

const el = document.getElementById( 'bookit-meetings-app' )
if ( el ) {
    createApp( App ).mount( el )
}