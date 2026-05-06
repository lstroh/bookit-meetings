<script setup>
const props = defineProps( {
	modelValue: { type: String, required: true },
	options: { type: Array, required: true },
} )

const emit = defineEmits( [ 'update:modelValue' ] )

function select( option ) {
	if ( option?.disabled ) return
	emit( 'update:modelValue', option.value )
}
</script>

<template>
	<div class="bm-platform">
		<div class="bm-platform__grid">
			<button
				v-for="opt in options"
				:key="opt.value"
				type="button"
				class="bm-platform__card"
				:class="{
					'is-selected': modelValue === opt.value,
					'is-disabled': !!opt.disabled,
				}"
				:disabled="!!opt.disabled"
				@click="select(opt)"
			>
				<div class="bm-platform__top">
					<div class="bm-platform__label">{{ opt.label }}</div>
					<span v-if="opt.comingSoon" class="bm-platform__badge">Coming Soon</span>
				</div>
			</button>
		</div>
	</div>
</template>

<style scoped>
.bm-platform__grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
}

@media (max-width: 640px) {
	.bm-platform__grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

.bm-platform__card {
	text-align: left;
	padding: 12px;
	border-radius: 12px;
	border: 1px solid var(--bookit-border-color);
	background: var(--bookit-bg-card);
	color: var(--bookit-text-primary);
	cursor: pointer;
	transition: border-color 150ms ease, background-color 150ms ease, opacity 150ms ease;
}

.bm-platform__card.is-selected {
	border-color: var(--bookit-color-primary);
	background: color-mix(in srgb, var(--bookit-color-primary) 12%, var(--bookit-bg-card));
}

.bm-platform__card.is-disabled {
	opacity: 0.55;
	cursor: not-allowed;
}

.bm-platform__top {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
}

.bm-platform__label {
	font-weight: 600;
}

.bm-platform__badge {
	display: inline-flex;
	align-items: center;
	padding: 2px 8px;
	border-radius: 999px;
	border: 1px solid var(--bookit-border-color);
	color: var(--bookit-text-secondary);
	font-size: 11px;
	white-space: nowrap;
}
</style>

