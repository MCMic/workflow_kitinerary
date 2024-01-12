<template>
	<NcSelect v-model="currentValue"
		:options="values"
		track-by="id"
		:internal-search="true"
		label="text"
		:placeholder="placeholderString"
		@input="onInput" />
</template>

<script>
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'WorkflowKitinerary',
	components: { NcSelect },
	props: {
		value: {
			default: '',
			type: String,
		},
	},
	data() {
		const options = loadState('workflow_kitinerary', 'userCalendars')
		let currentValue
		if (this.value) {
			currentValue = options[this.value]
		} else {
			currentValue = null
		}
		return {
			options,
			currentValue,
		}
	},
	computed: {
		placeholderString() {
			// TRANSLATORS: Users should select a calendar for a kitinerary workflow action
			return t('workflow_kitinerary', 'Select a calendar')
		},
		values() {
			return Object.keys(this.options).map(id => {
				return {
					id,
					text: this.options[id],
				}
			})
		},
	},
	methods: {
		onInput(newValue) {
			// when clicking on the an already selected item, we get null
			// this avoids unselecting an item
			if (newValue !== null) {
				this.$emit('input', newValue.id)
			}
		},
	},
}
</script>
