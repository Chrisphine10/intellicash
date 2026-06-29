export interface OfflineVerifier {
  memberId: string;
  fullName: string;
  role: string;
  verifier: string;
  pinUpdatedAt?: string | null;
}

export interface OfflineMeetingLedgerEntry {
  memberId: string;
  type: "SHARE_PURCHASE" | "LOAN_REPAYMENT" | "INTERNAL_LOAN_DISBURSEMENT" | "SOCIAL_CONTRIBUTION" | "SHARE_OUT_PAYOUT";
  amountCents: number;
  description?: string;
  externalReference?: string;
  clientRequestId?: string;
}

export interface OfflineMeetingDraft {
  groupId: string;
  meetingId: string;
  deviceId: string;
  keySubmissions: Array<{
    memberId?: string;
    pin: string;
    credentialType?: "DEFAULT_PIN" | "CURRENT_OTP";
    deviceId?: string;
    capturedOfflineAt?: string;
  }>;
  attendance: Array<{
    memberId: string;
    status: "PRESENT" | "ABSENT" | "LATE" | "EXCUSED";
    clientRequestId?: string;
  }>;
  ledgerEntries: OfflineMeetingLedgerEntry[];
  savedAt: string;
}

const dbName = "intellicash-meeting-pwa";
const dbVersion = 2;
const verifierStore = "verifiers";
const draftStore = "drafts";
const workspaceStore = "group-meeting-workspaces";
const scheduleQueueStore = "meeting-schedule-queue";
const deviceStorageKey = "intellicash-meeting-device-id";

export interface OfflineGroupMeetingWorkspace<
  TGroup = unknown,
  TMeeting = unknown,
  TMember = unknown,
  TUser = unknown
> {
  group: TGroup;
  meetings: TMeeting[];
  members: TMember[];
  user: TUser;
  cachedAt: string;
}

export interface OfflineMeetingScheduleDraft {
  id: string;
  groupId: string;
  title: string;
  scheduledAt: string;
  gpsCompliant: boolean;
  createdAt: string;
}

interface StoredEncryptedPayload {
  encrypted: true;
  iv: string;
  payload: string;
}

interface StoredPlainPayload<T> {
  encrypted: false;
  payload: T;
}

type StoredPayload<T> = StoredEncryptedPayload | StoredPlainPayload<T>;

function bytesToBase64(bytes: Uint8Array) {
  let binary = "";
  bytes.forEach((byte) => {
    binary += String.fromCharCode(byte);
  });
  return btoa(binary);
}

function base64ToBytes(value: string) {
  const binary = atob(value);
  return Uint8Array.from(binary, (char) => char.charCodeAt(0));
}

async function sha256Hex(value: string) {
  const bytes = new TextEncoder().encode(value);
  const hash = await crypto.subtle.digest("SHA-256", bytes);
  return Array.from(new Uint8Array(hash))
    .map((byte) => byte.toString(16).padStart(2, "0"))
    .join("");
}

async function encryptionKey(deviceId: string) {
  const hash = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(`intellicash:${deviceId}`));
  return crypto.subtle.importKey("raw", hash, "AES-GCM", false, ["encrypt", "decrypt"]);
}

async function encryptJson<T>(deviceId: string, value: T): Promise<StoredPayload<T>> {
  if (!globalThis.crypto?.subtle) return { encrypted: false, payload: value };
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const key = await encryptionKey(deviceId);
  const encoded = new TextEncoder().encode(JSON.stringify(value));
  const encrypted = await crypto.subtle.encrypt({ name: "AES-GCM", iv }, key, encoded);
  return {
    encrypted: true,
    iv: bytesToBase64(iv),
    payload: bytesToBase64(new Uint8Array(encrypted))
  };
}

async function decryptJson<T>(deviceId: string, value: StoredPayload<T>): Promise<T> {
  if (!value.encrypted) return value.payload;
  const key = await encryptionKey(deviceId);
  const decrypted = await crypto.subtle.decrypt(
    { name: "AES-GCM", iv: base64ToBytes(value.iv) },
    key,
    base64ToBytes(value.payload)
  );
  return JSON.parse(new TextDecoder().decode(decrypted)) as T;
}

function openDb() {
  return new Promise<IDBDatabase>((resolve, reject) => {
    const request = indexedDB.open(dbName, dbVersion);
    request.onupgradeneeded = () => {
      const db = request.result;
      if (!db.objectStoreNames.contains(verifierStore)) db.createObjectStore(verifierStore);
      if (!db.objectStoreNames.contains(draftStore)) db.createObjectStore(draftStore);
      if (!db.objectStoreNames.contains(workspaceStore)) db.createObjectStore(workspaceStore);
      if (!db.objectStoreNames.contains(scheduleQueueStore)) db.createObjectStore(scheduleQueueStore);
    };
    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
  });
}

async function idbSet(storeName: string, key: string, value: unknown) {
  const db = await openDb();
  await new Promise<void>((resolve, reject) => {
    const tx = db.transaction(storeName, "readwrite");
    tx.objectStore(storeName).put(value, key);
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
  db.close();
}

async function idbGet<T>(storeName: string, key: string) {
  const db = await openDb();
  const result = await new Promise<T | null>((resolve, reject) => {
    const tx = db.transaction(storeName, "readonly");
    const request = tx.objectStore(storeName).get(key);
    request.onsuccess = () => resolve((request.result as T | undefined) ?? null);
    request.onerror = () => reject(request.error);
  });
  db.close();
  return result;
}

async function idbDelete(storeName: string, key: string) {
  const db = await openDb();
  await new Promise<void>((resolve, reject) => {
    const tx = db.transaction(storeName, "readwrite");
    tx.objectStore(storeName).delete(key);
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
  db.close();
}

function storageFallbackKey(storeName: string, key: string) {
  return `${dbName}:${storeName}:${key}`;
}

async function setStored<T>(storeName: string, key: string, value: T) {
  try {
    await idbSet(storeName, key, value);
  } catch {
    localStorage.setItem(storageFallbackKey(storeName, key), JSON.stringify(value));
  }
}

async function getStored<T>(storeName: string, key: string) {
  try {
    return await idbGet<T>(storeName, key);
  } catch {
    const value = localStorage.getItem(storageFallbackKey(storeName, key));
    return value ? (JSON.parse(value) as T) : null;
  }
}

async function deleteStored(storeName: string, key: string) {
  try {
    await idbDelete(storeName, key);
  } catch {
    localStorage.removeItem(storageFallbackKey(storeName, key));
  }
}

export function getMeetingDeviceId() {
  const existing = localStorage.getItem(deviceStorageKey);
  if (existing) return existing;
  const generated =
    globalThis.crypto?.randomUUID?.() ??
    `meeting-device-${Date.now()}-${Math.random().toString(36).slice(2)}`;
  localStorage.setItem(deviceStorageKey, generated);
  return generated;
}

export async function storeOfflineVerifiers(deviceId: string, verifiers: OfflineVerifier[]) {
  const payload = await encryptJson(deviceId, {
    deviceId,
    verifiers,
    cachedAt: new Date().toISOString()
  });
  await setStored(verifierStore, deviceId, payload);
}

export async function loadOfflineVerifiers(deviceId: string) {
  const stored = await getStored<StoredPayload<{ deviceId: string; verifiers: OfflineVerifier[]; cachedAt: string }>>(
    verifierStore,
    deviceId
  );
  if (!stored) return [];
  const decrypted = await decryptJson(deviceId, stored);
  return decrypted.verifiers;
}

export async function verifyOfflinePin(deviceId: string, memberId: string, pin: string) {
  if (!globalThis.crypto?.subtle) return false;
  const verifiers = await loadOfflineVerifiers(deviceId);
  const verifier = await sha256Hex(`${deviceId}:${memberId}:${pin}`);
  return verifiers.some((row) => row.memberId === memberId && row.verifier === verifier);
}

export async function queueMeetingDraft(draft: OfflineMeetingDraft) {
  const current = await loadMeetingDraft(draft.groupId, draft.meetingId, draft.deviceId);
  const merged: OfflineMeetingDraft = {
    ...draft,
    keySubmissions: [...(current?.keySubmissions ?? []), ...draft.keySubmissions],
    attendance: [...(current?.attendance ?? []), ...draft.attendance],
    ledgerEntries: [...(current?.ledgerEntries ?? []), ...draft.ledgerEntries],
    savedAt: new Date().toISOString()
  };
  await setStored(draftStore, `${draft.groupId}:${draft.meetingId}:${draft.deviceId}`, merged);
  return merged;
}

export async function loadMeetingDraft(groupId: string, meetingId: string, deviceId: string) {
  return getStored<OfflineMeetingDraft>(draftStore, `${groupId}:${meetingId}:${deviceId}`);
}

export async function clearMeetingDraft(groupId: string, meetingId: string, deviceId: string) {
  await deleteStored(draftStore, `${groupId}:${meetingId}:${deviceId}`);
}

function offlineMeetingId() {
  return `offline-meeting-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
}

export async function storeGroupMeetingWorkspace<TGroup, TMeeting, TMember, TUser>(
  groupId: string,
  workspace: OfflineGroupMeetingWorkspace<TGroup, TMeeting, TMember, TUser>
) {
  await setStored(workspaceStore, groupId, workspace);
}

export async function loadGroupMeetingWorkspace<TGroup, TMeeting, TMember, TUser>(groupId: string) {
  return getStored<OfflineGroupMeetingWorkspace<TGroup, TMeeting, TMember, TUser>>(workspaceStore, groupId);
}

export async function loadOfflineMeetingSchedules(groupId: string) {
  return (await getStored<OfflineMeetingScheduleDraft[]>(scheduleQueueStore, groupId)) ?? [];
}

export async function queueOfflineMeetingSchedule(input: {
  groupId: string;
  title: string;
  scheduledAt: string;
  gpsCompliant?: boolean;
}) {
  const queued: OfflineMeetingScheduleDraft = {
    id: offlineMeetingId(),
    groupId: input.groupId,
    title: input.title,
    scheduledAt: input.scheduledAt,
    gpsCompliant: input.gpsCompliant ?? false,
    createdAt: new Date().toISOString()
  };
  const current = await loadOfflineMeetingSchedules(input.groupId);
  await setStored(scheduleQueueStore, input.groupId, [...current, queued]);
  return queued;
}

export async function clearOfflineMeetingSchedule(groupId: string, scheduleId: string) {
  const current = await loadOfflineMeetingSchedules(groupId);
  const remaining = current.filter((schedule) => schedule.id !== scheduleId);
  if (remaining.length === 0) {
    await deleteStored(scheduleQueueStore, groupId);
    return;
  }
  await setStored(scheduleQueueStore, groupId, remaining);
}
