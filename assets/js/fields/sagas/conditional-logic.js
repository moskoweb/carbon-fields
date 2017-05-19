/**
 * The external dependencies.
 */
import { takeEvery } from 'redux-saga';
import { put, call, select } from 'redux-saga/effects';
import { isEmpty, omit, some, every, includes, isUndefined } from 'lodash';

/**
 * The internal dependencies.
 */
import { setupField, updateField, setUI } from 'fields/actions';
import { getFieldById, makeGetFieldsByParent } from 'fields/selectors';

/**
 * Compare the values.
 *
 * @param  {mixed}   left
 * @param  {mixed}   right
 * @param  {String}  operator
 * @return {Boolean}
 */
function compare(left, right, operator) {
	switch (operator) {
		case '=': return left == right;
		case '!=': return left != right;
		case '>': return left > right;
		case '<': return left < right;
		case '>=': return left >= right;
		case '<=': return left <= right;
		case 'IN': return some(right, value => value == left);
		case 'NOT IN': return every(right, value => value != left);
		case 'INCLUDES': return every([].concat(right), value => left.indexOf(value) !== -1);
		case 'EXCLUDES': return every([].concat(right), value => left.indexOf(value) === -1);
	}
}

/**
 * Process the conditional rules.
 *
 * @param  {Object} field
 * @param  {Object} siblings
 * @param  {Object} [action]
 * @param  {Object} action.payload
 * @param  {String} action.payload.fieldId
 * @param  {mixed}  action.payload.value
 * @return {void}
 */
export function* workerValidate(field, siblings, { payload: { fieldId, data } } = { payload: {} }) {
	if (fieldId && (isUndefined(data.value) || !includes(siblings, fieldId))) {
		return;
	}

	const { relation, rules } = field.conditional_logic;
	const results = [];
	let valid;

	for (const rule of rules) {
		const { value } = yield select(getFieldById, siblings[`_${rule.field}`]);
		const result = yield call(compare, value, rule.value, rule.compare);

		results.push(result);
	}

	switch (relation) {
		case 'AND':
			valid = every(results);
		break;

		case 'OR':
			valid = some(results);
		break;
	}

	yield put(setUI(field.id, {
		is_visible: valid,
	}));
}

/**
 * Handle the setup of the conditional logic.
 *
 * @param  {Object} action
 * @param  {Object} action.payload
 * @param  {String} action.payload.fieldId
 * @return {void}
 */
export function* workerConditionalLogic({ payload: { fieldId } }) {
	const field = yield select(getFieldById, fieldId);

	if (isEmpty(field.conditional_logic)) {
		return;
	}

	const selector = yield call(makeGetFieldsByParent, field.parent)
	const siblings = yield call(omit, yield select(selector), field.name);

	yield call(workerValidate, field, siblings);
	yield takeEvery(updateField, workerValidate, field, siblings);
}

/**
 * Start to work.
 *
 * @return {void}
 */
export default function* foreman() {
	yield [
		takeEvery(setupField, workerConditionalLogic),
	];
}
