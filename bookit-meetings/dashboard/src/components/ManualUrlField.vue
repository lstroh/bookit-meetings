<script setup>
import { computed } from 'vue'

const props = defineProps( {
	modelValue: { type: String, required: true },
	label: { type: String, required: true },
	placeholder: { type: String, default: '' },
} )

const emit = defineEmits( [ 'update:modelValue' ] )

const value = computed( {
	get() {
		return props.modelValue
	},
	set( v ) {
		emit( 'update:modelValue', String( v ?? '' ) )
	},
} )

const error = computed( () => {
	const v = String( props.modelValue ?? '' ).trim()
	if ( ! v ) return ''
	try {
		// eslint-disable-next-line no-new
		new URL( v )
		return ''
	} catch {
		return 'Please enter a valid URL.'
	}
} )
</script>

<template>
	<div class="bm-field">
		<label class="bm-field__label">{{ label }}</label>
		<input
			v-model="value"
			class="bm-field__input"
			type="url"
			:placeholder="placeholder"
			autocomplete="off"
		/>
		<div v-if="error" class="bm-field__error">{{ error }}</div>
	</div>
</template>

<style scoped>
.bm-field__label {
	display: block;
	margin-bottom: 6px;
	color: var(--bookit-text-primary);
	font-weight: 600;
}

.bm-field__input {
	width: 100%;
	border-radius: 10px;
	padding: 10px 12px;
	border: 1px solid var(--bookit-border-color);
	background: var(--bookit-bg-card);
	color: var(--bookit-text-primary);
	outline: none;
}

.bm-field__input:focus {
	border-color: var(--bookit-color-primary);
}

.bm-field__error {
	margin-top: 6px;
	color: var(--bookit-text-secondary);
	font-size: 12px;
}
</style>

