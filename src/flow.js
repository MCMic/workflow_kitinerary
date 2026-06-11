import { defineCustomElement } from 'vue'

import WorkflowKitinerary from './WorkflowKitinerary.vue'

const FlowKItineraryComponent = defineCustomElement(WorkflowKitinerary, { shadowRoot: false })
const customElementId = 'oca-workflow_kitinerary-operation-import'

window.customElements.define(customElementId, FlowKItineraryComponent)

OCA.WorkflowEngine.registerOperator({
	id: 'OCA\\WorkflowKitinerary\\Operation',
	operation: '',
	element: customElementId,
	color: '#4d4d4d',
})
