<script setup>
import { computed, ref } from 'vue'

const props = defineProps( {
	booking: { type: Object, required: true },
} )

const meetingsEnabled = computed( () => Boolean( window?.bookitMeetings?.meetings_enabled ) )
const platform = computed( () => String( window?.bookitMeetings?.meetings_platform ?? '' ) )

const meetingLink = computed( () => String( props.booking?.meeting_link ?? '' ).trim() )
const customerPhone = computed( () => String( props.booking?.customer_phone ?? '' ).trim() )

const shouldShowTeamsOrGeneric = computed(
	() => meetingsEnabled.value && ( platform.value === 'teams' || platform.value === 'generic' ) && meetingLink.value.length > 0
)
const shouldShowWhatsApp = computed( () => meetingsEnabled.value && platform.value === 'whatsapp' )

const copied = ref( false )
let copyTimer = null

async function copyToClipboard() {
	const text = meetingLink.value
	if ( ! text ) return

	try {
		if ( navigator?.clipboard?.writeText ) {
			await navigator.clipboard.writeText( text )
		} else {
			const ta = document.createElement( 'textarea' )
			ta.value = text
			ta.setAttribute( 'readonly', '' )
			ta.style.position = 'fixed'
			ta.style.opacity = '0'
			document.body.appendChild( ta )
			ta.select()
			document.execCommand( 'copy' )
			document.body.removeChild( ta )
		}

		copied.value = true
		if ( copyTimer ) window.clearTimeout( copyTimer )
		copyTimer = window.setTimeout( () => {
			copied.value = false
			copyTimer = null
		}, 2000 )
	} catch {
		// ignore
	}
}
</script>

<template>
	<div v-if="false" />

	<div v-if="shouldShowTeamsOrGeneric" class="bm-meeting">
		<div class="bm-meeting__title">Meeting</div>

		<div class="bm-meeting__row">
			<a class="bm-meeting__link" :href="meetingLink" target="_blank" rel="noopener noreferrer">Join Meeting</a>
			<button type="button" class="bm-meeting__copy" @click="copyToClipboard">
				{{ copied ? 'Copied' : 'Copy' }}
			</button>
		</div>

		<div class="bm-meeting__secondary">{{ meetingLink }}</div>
	</div>

	<div v-else-if="shouldShowWhatsApp" class="bm-meeting">
		<div class="bm-meeting__title">Meeting</div>

		<div class="bm-meeting__secondary">WhatsApp — call or video at appointment time</div>

		<div v-if="customerPhone" class="bm-meeting__row">
			<a class="bm-meeting__link" :href="`tel:${customerPhone}`">{{ customerPhone }}</a>
		</div>
	</div>
</template>

<style scoped>
.bm-meeting {
	background: var(--bookit-bg-card);
	border: 1px solid var(--bookit-border-color);
	border-radius: 14px;
	padding: 16px;
}

.bm-meeting__title {
	margin: 0 0 10px;
	color: var(--bookit-text-primary);
	font-weight: 600;
}

.bm-meeting__row {
	display: flex;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
}

.bm-meeting__link {
	color: var(--bookit-color-primary);
	text-decoration: underline;
	font-weight: 600;
}

.bm-meeting__copy {
	background: transparent;
	border: 1px solid var(--bookit-border-color);
	border-radius: 12px;
	padding: 8px 12px;
	cursor: pointer;
	color: var(--bookit-text-primary);
}

.bm-meeting__secondary {
	margin-top: 10px;
	color: var(--bookit-text-secondary);
	word-break: break-word;
}
</style>

