/**
 * WP Inactivity Logout Pro - Client-side Tracker
 * 
 * Gestisce il conteggio di inattività, il rilevamento accurato di eventi utente,
 * la prevenzione della comparsa del popup se l'utente è attivo, e il supporto a 0 per disattivare l'avviso.
 *
 * @package WPInactivityLogoutPro
 * @version 1.0.4
 * @author  PeopleInside
 */

(function () {
  'use strict';

  const config = window.wpInactivityData || null;
  if (!config) {
    return;
  }

  const timeoutMs = parseInt(config.timeoutMs, 10) || 1800000;
  const warningMs = parseInt(config.warningMs, 10) || 0;
  // Se warningMs è 0 o disabilitato, l'avviso popup non sarà mostrato
  const enableWarning = (config.enableWarningModal !== false) && (warningMs > 0) && (warningMs < timeoutMs);
  const remainingCountdownMs = enableWarning ? Math.max(1000, timeoutMs - warningMs) : 0;

  let lastActivityTime = Date.now();
  let warningTimer = null;
  let logoutTimer = null;
  let countdownInterval = null;
  let isModalVisible = false;
  let throttleResetTimeout = null;

  let broadcastChannel = null;
  if (config.enableMultiTabSync && typeof window.BroadcastChannel !== 'undefined') {
    try {
      broadcastChannel = new BroadcastChannel('wpinact_channel');
      broadcastChannel.onmessage = function (event) {
        if (!event.data) return;
        if (event.data.type === 'USER_ACTIVE') {
          handleRemoteActivity(event.data.timestamp);
        } else if (event.data.type === 'LOGOUT_NOW') {
          performLogout();
        }
      };
    } catch (e) {
      // Fallback
    }
  }

  // Ascolta gli aggiornamenti di attività da altre schede del browser tramite localStorage
  window.addEventListener('storage', function (e) {
    if (e.key === 'wpinact_last_activity' && e.newValue) {
      const remoteTime = parseInt(e.newValue, 10);
      if (remoteTime && remoteTime > lastActivityTime) {
        handleRemoteActivity(remoteTime);
      }
    }
  });

  function createModalDOM() {
    if (document.getElementById('wpinact-modal-overlay')) {
      return document.getElementById('wpinact-modal-overlay');
    }

    const overlay = document.createElement('div');
    overlay.id = 'wpinact-modal-overlay';
    overlay.className = 'wpinact-overlay wpinact-hidden';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', 'wpinact-modal-title');

    overlay.innerHTML = `
      <div class="wpinact-modal-box">
        <div class="wpinact-modal-header">
          <div class="wpinact-modal-icon">
            <svg viewBox="0 0 24 24" width="28" height="28" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
          </div>
          <h2 id="wpinact-modal-title" class="wpinact-title">${config.strings.modalTitle}</h2>
        </div>
        <div class="wpinact-modal-body">
          <p class="wpinact-message">${config.strings.modalMessage}</p>
          <div class="wpinact-countdown-container">
            <span class="wpinact-countdown-label">${config.strings.countdownLabel}</span>
            <div id="wpinact-countdown-timer" class="wpinact-timer-display">--:--</div>
            <div class="wpinact-progress-track">
              <div id="wpinact-progress-bar" class="wpinact-progress-fill"></div>
            </div>
          </div>
        </div>
        <div class="wpinact-modal-footer">
          <button type="button" id="wpinact-btn-logout" class="wpinact-btn wpinact-btn-secondary">
            ${config.strings.btnLogoutNow}
          </button>
          <button type="button" id="wpinact-btn-stay" class="wpinact-btn wpinact-btn-primary">
            ${config.strings.btnStayLogin}
          </button>
        </div>
      </div>
    `;

    document.body.appendChild(overlay);

    document.getElementById('wpinact-btn-stay').addEventListener('click', onStayLoggedIn);
    document.getElementById('wpinact-btn-logout').addEventListener('click', onLogoutClick);

    return overlay;
  }

  function playAlertSound() {
    if (!config.enableAudioAlert) return;
    try {
      const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      osc.connect(gain);
      gain.connect(audioCtx.destination);
      osc.type = 'sine';
      osc.frequency.setValueAtTime(587.33, audioCtx.currentTime);
      osc.frequency.setValueAtTime(880, audioCtx.currentTime + 0.15);
      gain.gain.setValueAtTime(0.08, audioCtx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.45);
      osc.start();
      osc.stop(audioCtx.currentTime + 0.45);
    } catch (e) {
      // Fallback
    }
  }

  // Verifica con estrema precisione se l'utente è davvero inattivo prima di mostrare il popup
  function checkAndShowWarning() {
    if (!enableWarning || isModalVisible) return;

    const now = Date.now();
    const elapsed = now - lastActivityTime;

    // SE C'È STATA ATTIVITÀ: NON MOSTRARE IL POPUP!
    if (elapsed < warningMs) {
      const remaining = warningMs - elapsed;
      warningTimer = setTimeout(checkAndShowWarning, Math.max(1000, remaining));
      return;
    }

    // Controlla se un'altra scheda aperta dell'utente è stata attiva di recente
    try {
      const remote = parseInt(localStorage.getItem('wpinact_last_activity'), 10) || 0;
      if (remote && (now - remote) < warningMs) {
        lastActivityTime = remote;
        const remaining = warningMs - (now - remote);
        warningTimer = setTimeout(checkAndShowWarning, Math.max(1000, remaining));
        return;
      }
    } catch (e) {}

    // L'utente è realmente inattivo: mostra il modal
    showWarningModal();
  }

  function showWarningModal() {
    if (isModalVisible || !enableWarning) return;
    isModalVisible = true;

    const overlay = createModalDOM();
    overlay.classList.remove('wpinact-hidden');
    overlay.classList.add('wpinact-visible');

    playAlertSound();

    const timerDisplay = document.getElementById('wpinact-countdown-timer');
    const progressBar = document.getElementById('wpinact-progress-bar');
    const deadline = Date.now() + remainingCountdownMs;

    function updateCountdown() {
      const now = Date.now();
      const remainingMs = Math.max(0, deadline - now);
      const totalSec = Math.ceil(remainingMs / 1000);
      const minutes = Math.floor(totalSec / 60);
      const seconds = totalSec % 60;

      if (timerDisplay) {
        timerDisplay.textContent = 
          (minutes < 10 ? '0' : '') + minutes + ':' + 
          (seconds < 10 ? '0' : '') + seconds;
      }

      if (progressBar) {
        const percent = Math.max(0, Math.min(100, (remainingMs / remainingCountdownMs) * 100));
        progressBar.style.width = percent + '%';
      }

      if (remainingMs <= 0) {
        clearInterval(countdownInterval);
        performLogout();
      }
    }

    updateCountdown();
    countdownInterval = setInterval(updateCountdown, 1000);
  }

  function hideWarningModal() {
    if (!isModalVisible) return;
    isModalVisible = false;

    if (countdownInterval) {
      clearInterval(countdownInterval);
      countdownInterval = null;
    }

    const overlay = document.getElementById('wpinact-modal-overlay');
    if (overlay) {
      overlay.classList.remove('wpinact-visible');
      overlay.classList.add('wpinact-hidden');
    }
  }

  function sendKeepAliveToServer() {
    const formData = new FormData();
    formData.append('action', 'wpinact_keepalive');
    formData.append('nonce', config.nonce);

    fetch(config.ajaxUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    }).catch(function (err) {
      console.warn('[WPInactivityLogout] Keepalive error:', err);
    });
  }

  function onStayLoggedIn() {
    hideWarningModal();
    resetTimers(true);
    sendKeepAliveToServer();

    if (broadcastChannel) {
      broadcastChannel.postMessage({ type: 'USER_ACTIVE', timestamp: Date.now() });
    }
  }

  function onLogoutClick() {
    if (broadcastChannel) {
      broadcastChannel.postMessage({ type: 'LOGOUT_NOW' });
    }
    performLogout();
  }

  function performLogout() {
    window.location.href = config.logoutUrl;
  }

  function checkAndPerformLogout() {
    const now = Date.now();
    const elapsed = now - lastActivityTime;

    // Se c'è stata attività recente, ricalcola
    if (elapsed < timeoutMs) {
      const remaining = timeoutMs - elapsed;
      logoutTimer = setTimeout(checkAndPerformLogout, Math.max(1000, remaining));
      return;
    }

    performLogout();
  }

  function handleRemoteActivity(timestamp) {
    lastActivityTime = timestamp || Date.now();
    if (isModalVisible) {
      hideWarningModal();
    }
    resetTimers(false);
  }

  function resetTimers(notifyBroadcast) {
    lastActivityTime = Date.now();

    try {
      localStorage.setItem('wpinact_last_activity', lastActivityTime.toString());
    } catch (e) {}

    if (warningTimer) clearTimeout(warningTimer);
    if (logoutTimer) clearTimeout(logoutTimer);

    if (enableWarning) {
      warningTimer = setTimeout(checkAndShowWarning, warningMs);
    }

    logoutTimer = setTimeout(checkAndPerformLogout, timeoutMs);

    if (notifyBroadcast && broadcastChannel) {
      broadcastChannel.postMessage({ type: 'USER_ACTIVE', timestamp: lastActivityTime });
    }
  }

  // Registra immediatamente qualsiasi evento di attività dell'utente
  function onUserActivity() {
    // Se il pop up di avviso è visibile, il conteggio non si resetta con un movimento casuale
    // L'utente deve esplicitamente cliccare su "Rimani connesso"
    if (isModalVisible) {
      return;
    }

    lastActivityTime = Date.now();

    try {
      localStorage.setItem('wpinact_last_activity', lastActivityTime.toString());
    } catch (e) {}

    // Throttle del reset dei timer a intervalli di 1 secondo per non sovraccaricare la CPU
    if (!throttleResetTimeout) {
      throttleResetTimeout = setTimeout(function () {
        throttleResetTimeout = null;
        resetTimers(true);
      }, 1000);
    }
  }

  // Monitora una serie completa di eventi di interazione utente su finestra e documento
  const events = [
    'mousemove', 'mousedown', 'mouseup', 'click', 'dblclick',
    'keydown', 'keypress', 'keyup',
    'touchstart', 'touchmove', 'touchend',
    'scroll', 'wheel',
    'pointerdown', 'pointermove', 'pointerup',
    'focus'
  ];

  events.forEach(function (eventName) {
    window.addEventListener(eventName, onUserActivity, { passive: true });
  });

  resetTimers(false);
})();
