<?php
/**
 * Front-end script (Buttons logic + price + UI mirroring) for SSPG plugin.
 * (Injects JS in footer)
 */

if (!defined('ABSPATH')) exit;

add_action('wp_footer', function () {
    if (!is_product()) return;
    global $product;
    if (!$product || !$product->is_type('booking')) return;
    if (!sspg_is_group_member($product->get_id())) return;

    $pid  = (int) $product->get_id();
    $ajax = admin_url('admin-ajax.php');
    $dest = (int) SSPG_DESTINATION_ID;
    $srcs = json_decode(SSPG_SOURCE_IDS, true) ?: [];
    ?>
<script>
(function(){
// ─────────────────────────────────────────────
// CONFIG / DOM
// ─────────────────────────────────────────────
const productId     = <?php echo $pid; ?>;
const destinationId = <?php echo $dest; ?>;
const sourceIds     = <?php echo json_encode(array_map('intval',$srcs)); ?>;
const ajaxUrl       = '<?php echo esc_js($ajax); ?>';
const useGroupAvail = <?php echo SSPG_BUTTONS_USE_GROUP_AVAIL ? 'true' : 'false'; ?>;

const slotButtonsEl = document.getElementById('sspg-slot-buttons');
const statusBox     = document.getElementById('sspg-slot-status');
const slotKeyInp    = document.getElementById('sspg_booking_slot_key');
const startInp      = document.getElementById('sspg_booking_start_time');
const endInp        = document.getElementById('sspg_booking_end_time');
const $  = (s) => document.querySelector(s);
const $$ = (s) => Array.from(document.querySelectorAll(s));

// ─────────────────────────────────────────────
// DATE + CALENDAR HELPERS
// ─────────────────────────────────────────────
function getDateInput(){
  return (
    $('.wc-bookings-date-picker input[type="text"]') ||
    $('.wc-bookings-date-picker input[type="date"]') ||
    $('input[name="wc_bookings_field_start_date"]') ||
    $('input[type="date"]')
  );
}
function getSelectedYMD(){
  const di = getDateInput();
  if (di && /^\d{4}-\d{2}-\d{2}$/.test(di.value)) return di.value;
  const y = $('input[name="wc_bookings_field_start_date_year"]')?.value;
  const m = $('input[name="wc_bookings_field_start_date_month"]')?.value;
  const d = $('input[name="wc_bookings_field_start_date_day"]')?.value;
  if (y && m && d) return `${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
  return '';
}
function getVisibleMonthYear(){
  const mEl = $('.ui-datepicker-title .ui-datepicker-month');
  const yEl = $('.ui-datepicker-title .ui-datepicker-year');
  if (!mEl || !yEl) return {year:null, month:null};
  const months = ['january','february','march','april','may','june','july','august','september','october','november','december'];
  const mi = months.indexOf(mEl.textContent.trim().toLowerCase());
  return {year: parseInt(yEl.textContent.trim(),10)||null, month: mi>=0 ? mi+1 : null};
}
function findTdForYMD(ymd){
  const m = ymd.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!m) return null;
  const y = parseInt(m[1],10), mo = parseInt(m[2],10), d = parseInt(m[3],10);
  const vis = getVisibleMonthYear();
  if (!vis.year || !vis.month || vis.year !== y || vis.month !== mo) return null;
  const cal = $('.ui-datepicker-calendar');
  if (!cal) return null;
  let link = cal.querySelector(`td[data-handler="selectDay"][data-year="${y}"][data-month="${mo-1}"] a.ui-state-default[data-date="${d}"]`);
  if (link) return link.closest('td');
  for (const cell of cal.querySelectorAll('td')) {
    if (cell.classList.contains('ui-datepicker-other-month')) continue;
    if (parseInt((cell.textContent||'').trim(),10) === d) return cell;
  }
  return null;
}
function ensureBookable(td){
  if (!td) return;
  td.classList.remove('ui-datepicker-unselectable','ui-state-disabled','fully_booked');
  td.classList.add('bookable');
  let a = td.querySelector('a.ui-state-default');
  if (!a) {
    const dayNum = parseInt(td.textContent.trim(),10);
    if (!Number.isNaN(dayNum)) {
      td.innerHTML = '';
      a = document.createElement('a');
      a.className = 'ui-state-default';
      a.href = '#';
      a.textContent = String(dayNum);
      td.appendChild(a);
    }
  }
}

// ─────────────────────────────────────────────
// SLOT + PRICE LOGIC
// ─────────────────────────────────────────────
function paintCellForSlot(slotKey){
  const ymd = getSelectedYMD();
  if (!ymd) return;
  const td = findTdForYMD(ymd);
  if (!td) return;
  td.classList.remove('ui-datepicker-unselectable','ui-state-disabled','fully_booked','morning_booked','afternoon_booked','full_day_booked','partial_booked');
  if (slotKey === 'fullday') { td.classList.add('full_day_booked','fully_booked'); return; }
  if (slotKey === 'morning') td.classList.add('morning_booked');
  if (slotKey === 'afternoon') td.classList.add('afternoon_booked');
  td.classList.add('partial_booked','bookable');
  ensureBookable(td);
}
function updatePrice(raw){
  const price = (parseFloat(raw)||0).toFixed(2);
  const priceBox  = document.getElementById('sspg-price-display');
  const valueSpan = priceBox?.querySelector('.sspg-price-value');
  if (valueSpan) {
    valueSpan.textContent = price;
  } else if (priceBox) {
    const currency = priceBox.querySelector('.woocommerce-Price-currencySymbol');
    if (currency) {
      if (!currency.nextSibling || currency.nextSibling.nodeType !== Node.TEXT_NODE) currency.insertAdjacentText('afterend', '');
      currency.nextSibling.nodeValue = price;
    } else {
      const amt = priceBox.querySelector('.woocommerce-Price-amount, .amount');
      if (amt) amt.textContent = price; else priceBox.textContent = 'Booking cost: £' + price;
    }
  }
  const costInput = $('input[name="wc_bookings_field_cost"]');
  if (costInput) costInput.value = price;
  if (window.jQuery) {
    jQuery(document.body).trigger('wc_bookings_cost_calculated', [price]);
    jQuery(document.body).trigger('wc_booking_form_changed');
  }
}
function renderSlots(slots){
  const hasFull = slots.includes('fullday');
  const hasM    = slots.includes('morning');
  const hasA    = slots.includes('afternoon');
  const both    = hasM && hasA;

  const morningBtn   = document.querySelector('.slot-btn[data-key="morning"]');
  const afternoonBtn = document.querySelector('.slot-btn[data-key="afternoon"]');
  const fullBtn      = document.querySelector('.slot-btn[data-key="fullday"]');

  if (morningBtn) {
      morningBtn.disabled = hasFull || hasM;
      morningBtn.classList.toggle('disabled', morningBtn.disabled);
  }
  if (afternoonBtn) {
      afternoonBtn.disabled = hasFull || hasA;
      afternoonBtn.classList.toggle('disabled', afternoonBtn.disabled);
  }
  if (fullBtn) {
      if (hasFull || hasM || hasA || both) {
          fullBtn.style.display = 'none';
      } else {
          fullBtn.style.display = '';
          fullBtn.disabled = false;
          fullBtn.classList.remove('disabled');
      }
  }

  let msg = '';
  if (hasFull) {
      msg = '<strong>Fully Booked</strong>';
  } else {
      msg += hasM ? 'Morning Unavailable<br>'   : 'Morning Available<br>';
      msg += hasA ? 'Afternoon Unavailable<br>' : 'Afternoon Available<br>';
      msg += (!hasM && !hasA) ? 'Full Day Available<br>' : 'Full Day Unavailable<br>';
  }
  if (statusBox) statusBox.innerHTML = msg;

  const ymd = getSelectedYMD();
  const td  = findTdForYMD(ymd);
  if (td) {
      td.classList.remove('morning_booked', 'afternoon_booked', 'full_day_booked', 'partial_booked');
      if (hasFull) {
          td.classList.add('full_day_booked', 'fully_booked');
      } else {
          if (hasM) td.classList.add('morning_booked');
          if (hasA) td.classList.add('afternoon_booked');
          if (hasM || hasA) td.classList.add('partial_booked');
      }
  }
}

function fetchSlots(ymd){
  if (!ymd) return;
  fetch(ajaxUrl, {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({action:'sspg_get_booking_slots', product_id:String(productId), date:ymd, scope: useGroupAvail ? 'group' : 'single'})
  }).then(r=>r.json())
    .then(j => { if (j && j.success) renderSlots(j.data||[]); else renderSlots([]); })
    .catch(()=>renderSlots([]));
}

function markCalendarFullDays(fullYmds){
  if (productId !== destinationId) return;
  const set = new Set(fullYmds||[]);
  const vis = getVisibleMonthYear();
  if (!vis.year || !vis.month) return;
  const cal = document.querySelector('.ui-datepicker-calendar');
  if (!cal) return;
  cal.querySelectorAll('td').forEach(td => {
    if (td.classList.contains('ui-datepicker-other-month')) return;
    const link = td.querySelector('a.ui-state-default');
    const day  = link?.dataset?.date ? parseInt(link.dataset.date,10) : parseInt((link?.textContent || td.textContent || '').trim(),10);
    if (!day) return;
    const ymd = `${vis.year}-${String(vis.month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
    if (set.has(ymd)) {
      td.classList.add('ui-datepicker-unselectable','ui-state-disabled','fully_booked','fully_booked_mirror');
      td.title = 'Fully booked (mirrored from other space)';
      if (link) {
        const span = document.createElement('span');
        span.className = link.className;
        span.textContent = link.textContent;
        link.replaceWith(span);
      }
    } else if (td.classList.contains('fully_booked_mirror')) {
      td.classList.remove('fully_booked','fully_booked_mirror','ui-datepicker-unselectable','ui-state-disabled');
      ensureBookable(td);
    }
  });
}
function fetchMonthFullDays(){
  if (productId !== destinationId) return;
  const vis = getVisibleMonthYear();
  if (!vis.year || !vis.month) return;
  fetch(ajaxUrl, {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({action:'sspg_get_month_full_days', product_id:String(productId), year:String(vis.year), month:String(vis.month)})
  }).then(r=>r.json())
    .then(j => { if (j && j.success) markCalendarFullDays(j.data||[]); });
}

function fixPartialDays(){
  if (productId === destinationId) return;
  document.querySelectorAll('.ui-datepicker-calendar td').forEach(td => {
    if (td.classList.contains('full_day_booked')) return;
    if ((td.classList.contains('morning_booked') || td.classList.contains('afternoon_booked'))
        && td.classList.contains('ui-state-disabled')) {
      td.classList.remove('ui-datepicker-unselectable','ui-state-disabled','fully_booked');
      td.classList.add('bookable');
      let a = td.querySelector('a.ui-state-default');
      if (!a) {
        const dayNum = parseInt(td.textContent.trim(),10);
        if (!isNaN(dayNum)) {
          td.innerHTML = '';
          a = document.createElement('a');
          a.className = 'ui-state-default';
          a.href = '#';
          a.textContent = String(dayNum);
          td.appendChild(a);
        }
      }
    }
  });
}

function onDateChange(){
  const ymd = getSelectedYMD();
  if (slotButtonsEl) slotButtonsEl.style.display = ymd ? 'block' : 'none';
  if (ymd) fetchSlots(ymd);
  fetchMonthFullDays();
  fixPartialDays();
}
$$('.slot-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('.slot-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    if (slotKeyInp) slotKeyInp.value = btn.dataset.key;
    if (startInp)   startInp.value   = btn.dataset.start || '';
    if (endInp)     endInp.value     = btn.dataset.end   || '';
    updatePrice(btn.dataset.price || '0.00');
    paintCellForSlot(btn.dataset.key);
  });
});
const di = getDateInput();
if (di) ['change','input','blur'].forEach(ev => di.addEventListener(ev, onDateChange));
const calRoot = document.querySelector('.wc-bookings-booking-form .wc-bookings-date-picker') || document.querySelector('.ui-datepicker');
if (calRoot) new MutationObserver(onDateChange).observe(calRoot, {childList:true, subtree:true});
onDateChange();
})();
</script>
<?php
});