// Local fixture owner/account 1 only; seed tests/fixtures/seed-pop3.py first.
const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const root=process.env.TEST_APP_URL || 'http://127.0.0.1:8000';
(async()=>{
  const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH || '/usr/bin/chromium',args:['--no-sandbox']});
  try {
    const page=await browser.newPage({viewport:{width:390,height:844}});
    const errors=[];page.on('pageerror',e=>errors.push(e.message));
    await page.goto(root+'/login');
    await page.locator('#username').fill('fixture-owner');
    await page.locator('#password').fill('fixture-owner-password');
    await page.getByRole('button',{name:'Inloggen',exact:true}).click();
    await page.waitForURL(root+'/');
    await page.goto(root+'/mail?account=1');
    assert.equal(await page.locator('.message-row').count(),10);
    const first=await page.locator('.message-row').evaluateAll(rows=>rows.map(r=>r.href));
    for(const colorScheme of ['light','dark']){
      await page.emulateMedia({colorScheme});
      assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
      assert(!/Warning:|Fatal error:/.test(await page.locator('body').innerText()));
    }
    await page.getByRole('link',{name:'Volgende',exact:true}).click();
    assert.equal(new URL(page.url()).searchParams.get('offset'),'10');
    assert.equal(await page.locator('.message-row').count(),10);
    const second=await page.locator('.message-row').evaluateAll(rows=>rows.map(r=>r.href));
    assert(!second.some(href=>first.includes(href)));
    await page.getByRole('link',{name:'Vorige',exact:true}).click();
    assert.deepEqual(await page.locator('.message-row').evaluateAll(rows=>rows.map(r=>r.href)),first);
    await page.locator('.message-row').first().click();
    assert((await page.locator('.mail-body').innerText()).includes('segment-body-'));
    await page.goto(root+'/mail?account=1&q=no-such-fixture-term');
    await page.getByRole('heading',{name:'Geen berichten in dit deel',exact:true}).waitFor();
    await page.getByRole('link',{name:'Volgende',exact:true}).click();
    assert.equal(new URL(page.url()).searchParams.get('offset'),'10');
    assert.equal(new URL(page.url()).searchParams.get('q'),'no-such-fixture-term');
    assert.equal(await page.locator('.message-row').count(),0);
    assert.equal(errors.length,0);
    console.log('POP3 browser: 10-message pages, next/previous, lazy content, empty search continuation and mobile light/dark: OK');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
