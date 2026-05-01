<script setup>
import { computed } from 'vue'

const props = defineProps( {
	modelValue: { type: Boolean, required: true },
	label: { type: String, required: true },
	description: { type: String, default: '' },
} )

const emit = defineEmits( [ 'update:modelValue' ] )

const value = computed( {
	get() {
		return props.modelValue
	},
	set( v ) {
		emit( 'update:modelValue', Boolean( v ) )
	},
} )
</script>

<template>
	<div class="bm-toggle">
		<div class="bm-toggle__text">
			<div class="bm-toggle__label">{{ label }}</div>
			<div v-if="description" class="bm-toggle__description">
				{{ description }}
			</div>
		</div>

		<button
			type="button"
			class="bm-toggle__switch"
			:class="{ 'is-on': value }"
			:aria-pressed="value ? 'true' : 'false'"
			@click="value = !value"
		>
			<span class="bm-toggle__thumb" />
		</button>
	</div>
</template>

<style scoped>
.bm-toggle {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	padding: 16px;
	border: 1px solid var(--bookit-border-color);
	border-radius: 12px;
	background: var(--bookit-bg-card);
}

.bm-toggle__label {
	color: var(--bookit-text-primary);
	font-weight: 600;
	line-height: 1.2;
}

.bm-toggle__description {
	margin-top: 4px;
	color: var(--bookit-text-secondary);
	font-size: 13px;
	line-height: 1.35;
}

.bm-toggle__switch {
	position: relative;
	width: 46px;
	height: 28px;
	border-radius: 999px;
	border: 1px solid var(--bookit-border-color);
	background: var(--bookit-border-color);
	cursor: pointer;
	transition: background-color 160ms ease, border-color 160ms ease;
	flex: 0 0 auto;
}

.bm-toggle__switch.is-on {
	background: var(--bookit-color-primary);
	border-color: var(--bookit-color-primary);
}

.bm-toggle__thumb {
	position: absolute;
	top: 50%;
	left: 3px;
	width: 22px;
	height: 22px;
	border-radius: 999px;
	transform: translate3d(0, -50%, 0);
	background: var(--bookit-bg-card);
	transition: transform 160ms ease;
}

.bm-toggle__switch.is-on .bm-toggle__thumb {
	transform: translate3d(18px, -50%, 0);
}
</style>

