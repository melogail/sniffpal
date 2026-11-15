import Tool from './components/Tool'

Nova.booting((app, store) => {
  app.component('ticket-thread', Tool)
})
