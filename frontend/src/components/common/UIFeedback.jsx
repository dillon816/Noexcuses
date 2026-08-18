import { createContext, useContext, useState, useCallback, useRef } from 'react';

// Fournit deux outils à toute l'application :
//   const toast = useToast();     toast('Enregistré', 'success')
//   const confirm = useConfirm(); if (await confirm({ message: '...' })) { ... }
// Remplace les alert()/confirm() natifs par des éléments cohérents avec la charte.

const UIContext = createContext(null);

export function useToast() {
  return useContext(UIContext).toast;
}
export function useConfirm() {
  return useContext(UIContext).confirm;
}

const TOAST_STYLES = {
  success: { bg: '#F0FDF4', border: '#BBF7D0', color: '#16A34A', icon: '✓' },
  error:   { bg: '#FEF2F2', border: '#FECACA', color: '#DC2626', icon: '⚠' },
  info:    { bg: '#EFF6FF', border: '#BFDBFE', color: '#2563EB', icon: 'ℹ' },
};

export function UIFeedbackProvider({ children }) {
  const [toasts, setToasts] = useState([]);
  const [confirmState, setConfirmState] = useState(null);
  const idRef = useRef(0);

  const removeToast = useCallback((id) => {
    setToasts((list) => list.filter((t) => t.id !== id));
  }, []);

  const toast = useCallback((message, type = 'success') => {
    const id = ++idRef.current;
    setToasts((list) => [...list, { id, message, type }]);
    setTimeout(() => removeToast(id), 3500);
  }, [removeToast]);

  const confirm = useCallback((opts) => {
    return new Promise((resolve) => setConfirmState({ ...opts, resolve }));
  }, []);

  const closeConfirm = (result) => {
    if (confirmState) confirmState.resolve(result);
    setConfirmState(null);
  };

  return (
    <UIContext.Provider value={{ toast, confirm }}>
      {children}

      {/* Pile de toasts en haut à droite */}
      <div style={{ position: 'fixed', top: 16, right: 16, zIndex: 9999, display: 'flex', flexDirection: 'column', gap: 8, maxWidth: 'calc(100vw - 32px)' }}>
        {toasts.map((t) => {
          const s = TOAST_STYLES[t.type] || TOAST_STYLES.info;
          return (
            <div key={t.id} role="status" className="ui-toast"
              style={{ background: s.bg, border: `1px solid ${s.border}`, color: s.color, borderRadius: 10, padding: '10px 14px', boxShadow: '0 4px 12px rgba(0,0,0,0.10)', display: 'flex', alignItems: 'center', gap: 8, fontSize: 14, fontWeight: 500 }}>
              <span style={{ fontWeight: 700 }}>{s.icon}</span>
              <span>{t.message}</span>
              <button onClick={() => removeToast(t.id)} aria-label="Fermer la notification"
                style={{ background: 'none', border: 'none', color: s.color, marginLeft: 4, fontSize: 16, lineHeight: 1, opacity: 0.6 }}>×</button>
            </div>
          );
        })}
      </div>

      {/* Modale de confirmation */}
      {confirmState && (
        <div role="dialog" aria-modal="true" onClick={() => closeConfirm(false)}
          style={{ position: 'fixed', inset: 0, background: 'rgba(15,23,42,0.45)', zIndex: 10000, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 }}>
          <div onClick={(e) => e.stopPropagation()} className="ui-modal"
            style={{ background: '#fff', borderRadius: 14, padding: 24, maxWidth: 380, width: '100%', boxShadow: '0 20px 50px rgba(0,0,0,0.25)' }}>
            <h3 style={{ fontSize: 18, fontWeight: 700, color: '#0F172A', marginBottom: 8 }}>{confirmState.title || 'Confirmer'}</h3>
            <p style={{ fontSize: 14, color: '#64748B', marginBottom: 20 }}>{confirmState.message}</p>
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10 }}>
              <button onClick={() => closeConfirm(false)}
                style={{ padding: '9px 16px', background: '#fff', color: '#334155', border: '1px solid #E2E8F0', borderRadius: 8, fontWeight: 600 }}>
                {confirmState.cancelLabel || 'Annuler'}
              </button>
              <button onClick={() => closeConfirm(true)} autoFocus
                style={{ padding: '9px 16px', background: confirmState.danger ? '#DC2626' : '#22C55E', color: '#fff', border: 'none', borderRadius: 8, fontWeight: 600 }}>
                {confirmState.confirmLabel || 'Confirmer'}
              </button>
            </div>
          </div>
        </div>
      )}
    </UIContext.Provider>
  );
}
