/* Fictional fixture universe. CONFIRMED is a demo status, never a fact about the owner. */
window.FIXTURES = {
  synthetic:true, date:'2026-10-07', timezone:'Europe/Moscow',
  facts:[
    {id:'F-12',status:'CONFIRMED',statement:'Built API regression tests with Python and pytest for the fictional Meridian billing service.',source:'Demo career note, approved 2026-09-15'},
    {id:'F-18',status:'CONFIRMED',statement:'Maintained PostgreSQL test fixtures and tested transaction boundaries at fictional Meridian.',source:'Demo project note, approved 2026-09-15'},
    {id:'F-23',status:'CONFIRMED',statement:'Has four years of commercial QA experience.',source:'Demo career history, approved 2026-09-16'},
    {id:'F-24',status:'CONFIRMED',statement:'English B2 and remote work in UTC+3.',source:'Demo preferences, confirmed 2026-09-16'},
    {id:'F-31',status:'PENDING',statement:'Led the migration of the QA platform.',source:'Unreviewed demo resume import. Scope and leadership unverified.'}
  ],
  applications:[
    {id:'APP-01',company:'Northstar Labs',initials:'N',role:'Senior QA Engineer',salary:'240–290k ₽',salaryBasis:'gross / month',format:'Remote · UTC+3',stage:'Prepare',recommendation:'APPLY',reason:'API and database testing are supported; Playwright depth needs clarification.',next:'Review resume change',due:'Today',track:'Quality Engineering'},
    {id:'APP-02',company:'Orbital Works',initials:'O',role:'QA Automation Engineer',salary:'220–260k ₽',salaryBasis:'gross / month',format:'Remote · UTC+3',stage:'Interview',recommendation:'APPLY',reason:'Python automation is supported; discuss release ownership at the interview.',next:'Prepare interview notes',due:'Tomorrow · 11:00',track:'Quality Engineering'},
    {id:'APP-03',company:'Cedar Systems',initials:'C',role:'Senior QA',salary:'250–300k ₽',salaryBasis:'gross / month',format:'Hybrid · Moscow',stage:'Communicate',recommendation:'MAYBE',reason:'Work format conflicts with the demo remote preference.',next:'Resolve work-format conflict',due:'Today',track:'Quality Engineering'},
    {id:'APP-04',company:'Prism Data',initials:'P',role:'QA Engineer',salary:'Not stated',salaryBasis:'unknown',format:'Remote · UTC+3',stage:'Analyze',recommendation:'MAYBE',reason:'Salary is unknown; explicit performance testing evidence is missing.',next:'Clarify salary',due:'No deadline',track:'Quality Engineering'},
    {id:'APP-05',company:'Lumen Cloud',initials:'L',role:'Senior QA Engineer',salary:'260–310k ₽',salaryBasis:'gross / month',format:'Remote · UTC+3',stage:'Apply',recommendation:'APPLY',reason:'Confirmed API evidence; demo package already approved.',next:'Record manual application',due:'Today',track:'Quality Engineering'},
    {id:'APP-06',company:'Harbor Tools',initials:'H',role:'QA Engineer',salary:'210–250k ₽',salaryBasis:'gross / month',format:'Remote · UTC+3',stage:'Discover',recommendation:'Not analyzed',reason:'Imported source has not been analyzed.',next:'Analyze saved source',due:'No deadline',track:'Quality Engineering'},
    {id:'APP-07',company:'Kite Studio',initials:'K',role:'QA Automation Engineer',salary:'230–270k ₽',salaryBasis:'gross / month',format:'Remote · UTC+3',stage:'Interview',recommendation:'APPLY',reason:'Automation evidence supported; prepare database examples.',next:'Prepare technical interview',due:'Friday · 15:00',track:'Quality Engineering'},
    {id:'APP-08',company:'Atlas Forge',initials:'A',role:'QA Engineer',salary:'200–240k ₽',salaryBasis:'gross / month',format:'Hybrid · Moscow',stage:'Outcome',recommendation:'SKIP',reason:'Demo outcome: candidate withdrew because of work format.',next:'Review outcome',due:'Closed',track:'Quality Engineering'}
  ],
  recommendation:{id:'R-07',status:'DRAFT',before:'Tested backend services.',after:'Built API regression tests with Python and pytest; tested PostgreSQL transaction boundaries.',claim:'CL-08',facts:['F-12','F-18'],reason:'Surface the API and database evidence requested by Northstar.',risk:'Does not support production performance testing or leadership claims.'},
  employerMemory:{confirmedAssociation:true,company:'Northstar Labs',events:[{date:'2026-10-02',text:'Demo recruiter note: API testing is the priority.'},{date:'2026-10-03',text:'Demo user confirmed remote-only preference and 260k ₽ gross expectation.'}],conflictCompany:'Cedar Systems',conflict:'Previous confirmed remote-only preference vs. a new hybrid-work draft.'}
};
