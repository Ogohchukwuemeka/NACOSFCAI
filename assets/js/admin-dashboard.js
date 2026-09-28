document.addEventListener('DOMContentLoaded', function(){
  // Sidebar toggle for small screens
  document.querySelectorAll('.hamburger').forEach(btn=>btn.addEventListener('click', ()=>{
    document.querySelector('.sidebar').classList.toggle('open');
  }));

  // Quick action modal content
  const modalTitle = document.getElementById('quickActionTitle');
  const modalBody = document.getElementById('quickActionBody');
  const modalLink = document.getElementById('quickActionLink');
  document.querySelectorAll('.action').forEach(btn=>btn.addEventListener('click', ()=>{
    const action = btn.getAttribute('data-action');
    const baseUrl = (window.APP_URL || '').replace(/\/$/, '');
    const config = {
      add_student: {title:'Add Student', body:'Create a new student account and onboard them into the NACOS FCAI portal.', link: baseUrl + '/auth/register.php'},
      verify_payment: {title:'Verify Payment', body:'Review pending receipts and confirm payment status from the payments management page.', link: baseUrl + '/admin/payments.php'},
      post_announcement: {title:'Post Announcement', body:'Send a new portal announcement to students and executives.', link: baseUrl + '/admin/dashboard.php'},
      create_event: {title:'Create Event', body:'Publish a new event and keep your student community informed.', link: baseUrl + '/admin/content.php'},
      upload_resource: {title:'Upload Resource', body:'Share course notes, PDFs, and study guides with the right level and semester.', link: baseUrl + '/admin/content.php'},
      generate_reports: {title:'Generate Reports', body:'Open the reporting workspace to view academic and payment insights.', link: baseUrl + '/admin/reports.php'}
    };
    const item = config[action] || config.generate_reports;
    if (modalTitle) modalTitle.textContent = item.title;
    if (modalBody) modalBody.innerHTML = '<p>' + item.body + '</p><p class="text-muted">The action opens the corresponding management area for quick updates.</p>';
    if (modalLink) {
      modalLink.href = item.link;
      modalLink.textContent = 'Open page';
    }
  }));

  // Charts initialization using server-provided data when available
  const dash = window.DASH_DATA || null;
  function fallbackRand(n){return Math.floor(Math.random()*n)+1}

  const regCtx = document.getElementById('registrationsChart');
  if(regCtx){
    const labels = (dash && dash.labels) ? dash.labels : ['Jan','Feb','Mar','Apr','May','Jun','Jul'];
    const dataPoints = (dash && dash.registrations) ? dash.registrations : [fallbackRand(120),fallbackRand(200),fallbackRand(160),fallbackRand(220),fallbackRand(180),fallbackRand(240),fallbackRand(200)];
    new Chart(regCtx, {
      type: 'line',
      data: { labels: labels, datasets: [{ label: 'Registrations', data: dataPoints, borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,0.08)', fill: true, tension: 0.32 }] },
      options: { plugins: { legend: { display: false } }, responsive: true, maintainAspectRatio: false }
    });
  }

  const payCtx = document.getElementById('paymentsChart');
  if(payCtx){
    const labels = (dash && dash.labels) ? dash.labels : ['Jan','Feb','Mar','Apr','May','Jun'];
    const dataPoints = (dash && dash.payments_sum) ? dash.payments_sum : [fallbackRand(40),fallbackRand(60),fallbackRand(30),fallbackRand(90),fallbackRand(70),fallbackRand(100)];
    new Chart(payCtx, {
      type: 'bar',
      data: { labels: labels, datasets: [{ label: 'Payments (₦)', data: dataPoints, backgroundColor: labels.map((l,i)=> ['#84CC16','#16a34a','#34d399','#86efac','#bbf7d0','#dcfce7'][i%6]) }] },
      options: { plugins: { legend: { display: false } }, responsive: true, maintainAspectRatio: false }
    });
  }

  const levelCtx = document.getElementById('levelChart');
  if(levelCtx){
    const rows = (dash && dash.students_by_level) ? dash.students_by_level : [];
    const labels = rows.map(item => item.level || 'Unspecified');
    const dataPoints = rows.map(item => Number(item.cnt) || 0);
    new Chart(levelCtx, {
      type: 'doughnut',
      data: { labels: labels, datasets: [{ data: dataPoints, backgroundColor: ['#84CC16','#166534','#34d399','#f59e0b','#f43f5e','#0f766e'] }] },
      options: { plugins: { legend: { position: 'bottom' } }, responsive: true, maintainAspectRatio: false }
    });
  }
});
