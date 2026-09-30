// Tests uitsluitend met fictieve accounts en de lokale mail-fixture.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const root = process.env.TEST_APP_URL || 'http://127.0.0.1:8000';
const store = process.env.TEST_STORAGE || '/tmp/news-mail-store';
(async () => {
  const browser = await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH || '/usr/bin/chromium',args:['--no-sandbox']});
  const page=await browser.newPage({viewport:{width:390,height:844}});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(root+'/');
  if(page.url().endsWith('/setup')) {
  await page.locator('#setup_token').fill(fs.readFileSync(store+'/setup-token','utf8').trim());
  await page.locator('#username').fill('fixture-owner');
  await page.locator('#password').fill('fixture-owner-password');
  await page.locator('#confirmation').fill('fixture-owner-password');
  await page.getByRole('button',{name:'Beheeraccount maken'}).click();
  await page.getByRole('heading',{name:'Homebase',exact:true}).waitFor();
  assert(!fs.existsSync(store+'/setup-token'));
  console.log('Beschermde installatie: OK');
  } else {
    await page.locator('#username').fill('fixture-owner');
    await page.locator('#password').fill('fixture-owner-password');
    await page.getByRole('button',{name:'Inloggen',exact:true}).click();
    await page.getByRole('heading',{name:'Homebase',exact:true}).waitFor();
  }
  await page.goto(root+'/mail/accounts');
  if(await page.locator('.account-card').count()===0) {
  for(const user of ['alpha','beta']) {
    await page.goto(root+'/mail/accounts/new');
    await page.locator('#incoming_protocol').selectOption('imap');
    await page.locator('#smtp_port').fill('587');
    await page.locator('#smtp_security').selectOption('tls');
    await page.locator('[name=smtp_accept_all]').uncheck();
    await page.locator('[name=imap_accept_all]').uncheck();
    await page.locator('#label').fill(user==='alpha'?'Werk':'Privé');
    await page.locator('#email').fill(user+'@example.test');
    await page.locator('#from_name').fill('Test '+user);
    for(const protocol of ['smtp','imap']) {
      await page.locator('#'+protocol+'_host').fill('mail-fixture');
      await page.locator('#'+protocol+'_user').fill(user);
      await page.locator('#'+protocol+'_password').fill('fixture-'+user+'-password');
    }
    await page.getByRole('button',{name:'Account opslaan'}).click();
    await page.getByRole('heading',{name:'Mailaccounts',exact:true}).waitFor();
  }
  }
  assert.equal(await page.locator('.account-card').count(),2);
  assert(!/fixture-(alpha|beta)-password/.test(await page.content()));
  await page.locator('.account-card').first().getByRole('button',{name:'Verbinding testen'}).click();
  await page.getByText('SMTP en IMAP zijn verbonden.').waitFor();
  console.log('Twee accounts en SMTP/IMAP-test: OK');
  await page.goto(root+'/mail/accounts/1');
  assert.equal(await page.locator('#smtp_password').inputValue(),'');
  await page.getByRole('button',{name:'Account opslaan'}).click();
  await page.getByRole('heading',{name:'Mailaccounts',exact:true}).waitFor();
  const unauthenticated=await browser.newContext();
  const denied=await unauthenticated.request.post(root+'/mcp',{data:{jsonrpc:'2.0',id:1,method:'tools/list'}});
  assert.equal(denied.status(),401);await unauthenticated.close();
  const deniedCsrf=await page.request.post(root+'/mail/accounts',{form:{label:'evil'}});
  assert.equal(deniedCsrf.status(),419);
  console.log('Wachtwoorden niet blootgesteld; login en CSRF: OK');
  await page.goto(root+'/mail?account=1');
  await page.getByText('Factuur voor alpha',{exact:true}).click();
  await page.getByText('Hallo alpha,',{exact:false}).waitFor();
  assert(!/Hallo beta/.test(await page.locator('.mail-body').innerText()));
  await page.getByRole('link',{name:'Antwoorden',exact:true}).click();
  assert.equal(await page.locator('#to').inputValue(),'supplier@example.test');
  await page.locator('#body').fill('Dank je, dit is alleen een lokale test.');
  await page.getByRole('button',{name:'Concept opslaan'}).click();
  await page.getByText('De ontvangers en inhoud kloppen; verstuur deze mail').waitFor();
  assert.equal(await page.locator('.mail-body').innerText(),'Dank je, dit is alleen een lokale test.');
  await page.getByRole('link',{name:'Concept bewerken',exact:true}).click();
  await page.locator('#body').fill('Bijgewerkt antwoord uit de browsertest.');
  await page.getByRole('button',{name:'Concept opslaan'}).click();
  await page.getByText('De ontvangers en inhoud kloppen; verstuur deze mail').waitFor();
  assert.equal(await page.locator('.mail-body').innerText(),'Bijgewerkt antwoord uit de browsertest.');
  console.log('Mailbox lezen, antwoorden en concept bewerken: OK');
  for(const width of [390,1024,1440])for(const scheme of ['light','dark']) {
    await page.setViewportSize({width,height:844});await page.emulateMedia({colorScheme:scheme});
    for(const path of ['/mail/accounts','/mail/accounts/new','/mail/compose','/connections','/security']) {
      await page.goto(root+path);
      const body=await page.locator('body').innerText();assert(!/Warning:|Fatal error:/.test(body));
      assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
      assert((await page.locator('.btn-primary').count())<=1);
      const background=await page.evaluate(()=>getComputedStyle(document.body).backgroundColor);
      assert.equal(background,scheme==='light'?'rgb(250, 249, 246)':'rgb(33, 32, 28)');
    }
    await page.goto(root+'/mail/accounts');
    await page.screenshot({path:`/tmp/mail-accounts-${width}-${scheme}.png`,fullPage:true});
    console.log(`${width}px ${scheme}: alle mailpagina’s OK`);
  }
  assert.deepEqual(errors,[]);
  await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
