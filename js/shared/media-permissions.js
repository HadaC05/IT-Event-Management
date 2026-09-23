(() => {
  'use strict';
  const roleLabel = role => role === 'SBO' ? 'Admin' : (role || 'CITE Member');
  const roleTone = role => ({
    Admin: 'bg-[#121017] text-white',
    SBO: 'bg-[#121017] text-white',
    'SBO Adviser': 'bg-[#C6F24E]/45 text-[#397565]',
    'SBO Officer': 'bg-[#2F3AE0]/10 text-[#2F3AE0]',
    Faculty: 'bg-[#397565]/10 text-[#397565]',
    Student: 'bg-[#F3F0E9] text-[#121017]/65',
  }[role] || 'bg-[#F3F0E9] text-[#121017]/65');
  const specialTagMarkup = tag => tag === 'Dev'
    ? '<span class="rounded bg-[#2F3AE0]/10 px-2 py-0.5 text-[10px] font-black text-[#2F3AE0]" aria-label="Developer">Dev</span>'
    : '';
  const canEdit = (post, viewer) => Number(post.user_id) === Number(viewer.id) && post.status !== 'hidden';
  const canDelete = (post, viewer) => Number(post.user_id) === Number(viewer.id) && post.status !== 'hidden';
  window.CiteMediaPermissions = {roleLabel, roleTone, specialTagMarkup, canEdit, canDelete};
})();
