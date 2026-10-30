import test from 'node:test'
import assert from 'node:assert/strict'
import * as THREE from 'three'
import { getItemStatusVisual } from '../../resources/js/utils/itemStatusVisual.js'
import { createModels } from '../../resources/js/utils/itemStatusModels.js'
const today = '2026-10-30'
test('visual state follows occurred dates while expiry remains distinct from disposal', () => {
    const item = { purchased_at: '2026-10-01', received_at: '2026-10-02', used_at: '2026-10-03' }
    assert.equal(getItemStatusVisual({ purchased_at: item.purchased_at }, today), 'waiting')
    assert.equal(getItemStatusVisual({ purchased_at: item.purchased_at, received_at: item.received_at }, today), 'idle')
    assert.equal(getItemStatusVisual(item, today), 'using')
    assert.equal(getItemStatusVisual({ ...item, expiration_date: '2026-10-29' }, today), 'expired')
    assert.equal(getItemStatusVisual({ ...item, expiration_date: '2026-10-29', discarded_at: today }, today), 'discarded')
    assert.equal(item.discarded_at, undefined)
})
test('future and invalid records do not animate completed events', () => {
    assert.equal(getItemStatusVisual({}, today), null)
    assert.equal(getItemStatusVisual({ purchased_at: '2026-11-01' }, today), null)
    assert.equal(getItemStatusVisual({ purchased_at: '2026-10-01', received_at: '2026-11-01' }, today), 'waiting')
    assert.equal(getItemStatusVisual({ used_at: '2026-10-01', discarded_at: '2026-11-01' }, today), 'using')
    assert.equal(getItemStatusVisual({ purchased_at: '2026-10-02', received_at: '2026-10-01' }, today), null)
    assert.equal(getItemStatusVisual({ used_at: '2026-02-30' }, today), null)
})
test('status poses keep arms connected, wheel axles stable, faces clear and delivery paths outside truck', () => {
    const scene = new THREE.Scene()
    const colors = Object.fromEntries(['brand', 'surface', 'text', 'text-muted', 'border-control', 'illustration-skin', 'illustration-paper', 'illustration-wood'].map(key => [key, '#777777']))
    const models = createModels(scene, colors)
    try {
        for (const state of ['waiting', 'idle', 'using', 'discarded', 'expired']) {
            for (let frame = 0; frame <= 60; frame++) {
                const t = frame / 60, pose = models.poseFor(state, t)
                models.apply(pose); scene.updateMatrixWorld(true)
                for (const arm of models.arms) {
                    const upperEnd = new THREE.Vector3(0, .5, 0).applyMatrix4(arm.upper.matrixWorld)
                    const lowerStart = new THREE.Vector3(0, -.5, 0).applyMatrix4(arm.lower.matrixWorld)
                    const wrist = new THREE.Vector3(0, .5, 0).applyMatrix4(arm.lower.matrixWorld)
                    assert.ok(upperEnd.distanceTo(lowerStart) < 1e-6)
                    assert.ok(wrist.distanceTo(arm.hand.getWorldPosition(new THREE.Vector3())) < 1e-6)
                }
                for (const wheel of models.wheels) assert.ok(Math.abs(new THREE.Vector3(0, 1, 0).transformDirection(wheel.children[0].matrixWorld).z) > .9999)
                if (state === 'using') assert.ok(new THREE.Vector3(0, 1.42, 0).applyMatrix4(models.actor.matrixWorld).distanceTo(models.parcel.position) > .55)
                if (state === 'idle' && t > .2 && t < .96 && models.truck.visible) {
                    assert.equal(new THREE.Box3().setFromObject(models.actor.children[0]).intersectsBox(new THREE.Box3().setFromObject(models.truck)), false)
                }
                if (state === 'discarded' && t > .48 && pose.itemVisible) {
                    assert.ok(Math.abs(pose.item[0] - .55) < 1e-8)
                    assert.ok(Math.abs(pose.item[2] - .15) < 1e-8)
                }
            }
        }
        scene.traverse(object => { if (object.material) assert.equal(object.material.transparent, false) })
    } finally { scene.traverse(object => { object.geometry?.dispose(); object.material?.dispose() }) }
})
