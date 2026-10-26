"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.isNonNullable = isNonNullable;
exports.isReactRenderable = isReactRenderable;
/**
 * Check if a value is neither `null` nor `undefined`.
 */
function isNonNullable(value) {
  return value !== undefined && value !== null;
}

/**
 * Check whether a value should be treated as renderable content.
 *
 * Returns `false` only for `null`, `undefined`, `false`, and `''`; all other
 * values, including `0` and `true`, are treated as renderable.
 *
 * This is a compatibility-oriented presence check, not a complete React node
 * validator.
 */
function isReactRenderable(value) {
  return isNonNullable(value) && value !== false && value !== '';
}