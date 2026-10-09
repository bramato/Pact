import { requireRule, sha256, validateEvent } from './validation.mjs';

// Conformance models only. Production adapters need transactional durable storage.
function trustedScope(...ids) {
  requireRule(ids.every(id => typeof id === 'string' && id.length > 0),
    'SECURITY_SCOPE', 'Authenticated tenant and peer scope are required');
}

export class EventJournal {
  constructor(snapshot = { receipts: [], positions: [] }) {
    this.receipts = new Map(snapshot.receipts);
    this.positions = new Map(snapshot.positions);
  }

  accept({ tenant, producerId, raw }) {
    trustedScope(tenant, producerId);
    const event = JSON.parse(raw);
    requireRule(event?.producer_id === producerId, 'PRODUCER_IDENTITY', 'Event producer differs from authenticated peer');
    const receiptKey = JSON.stringify([tenant, producerId, event.event_id]);
    const digest = sha256(raw);
    if (this.receipts.has(receiptKey)) {
      requireRule(this.receipts.get(receiptKey) === digest, 'EVENT_CONFLICT', 'Event ID was reused with different bytes');
      return { outcome: 'duplicate', applied: false };
    }
    validateEvent(event);
    const positionKey = JSON.stringify([tenant, producerId, event.task_id]);
    const previous = this.positions.get(positionKey);
    this.receipts.set(receiptKey, digest);
    if (previous !== undefined && event.sequence <= previous) {
      return { outcome: 'stale', applied: false };
    }
    this.positions.set(positionKey, event.sequence);
    return { outcome: 'accepted', applied: true };
  }

  snapshot() {
    return { receipts: [...this.receipts], positions: [...this.positions] };
  }
}

export class IdempotencyStore {
  constructor(snapshot = []) { this.receipts = new Map(snapshot); }

  accept({ tenant, callerId, key, raw, createTask }) {
    trustedScope(tenant, callerId, key);
    const receiptKey = JSON.stringify([tenant, callerId, key]);
    const digest = sha256(raw);
    const previous = this.receipts.get(receiptKey);
    if (previous) {
      requireRule(previous.digest === digest, 'COMMAND_CONFLICT', 'Idempotency key was reused with different bytes');
      return { taskId: previous.taskId, duplicate: true };
    }
    const taskId = createTask();
    requireRule(typeof taskId === 'string' && taskId.length > 0, 'TASK_ID', 'Synchronous fixture task creation must return an ID');
    this.receipts.set(receiptKey, { digest, taskId });
    return { taskId, duplicate: false };
  }

  snapshot() { return structuredClone([...this.receipts]); }
}
