import { NextRequest, NextResponse } from 'next/server'
import nodemailer from 'nodemailer'

export async function POST(request: NextRequest) {
  try {
    const body = await request.json()
    const { name, email, company, phone, message } = body

    // Validation
    if (!name || !email || !message) {
      return NextResponse.json(
        { error: 'Name, E-Mail und Nachricht sind Pflichtfelder' },
        { status: 400 }
      )
    }

    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(email)) {
      return NextResponse.json(
        { error: 'Bitte geben Sie eine gültige E-Mail-Adresse ein' },
        { status: 400 }
      )
    }

    // Create transporter (using environment variables for security)
    const port = parseInt(process.env.SMTP_PORT || '587')
    const transporter = nodemailer.createTransport({
      host: process.env.SMTP_HOST || 'smtp.gmail.com',
      port: port,
      secure: port === 465, // true für Port 465 (SSL), false für Port 587 (STARTTLS)
      auth: {
        user: process.env.SMTP_USER,
        pass: process.env.SMTP_PASS,
      },
    })

    // Prepare email content
    const emailContent = `
Neue Kontaktanfrage von der PräsenzWert Website

Name: ${name}
E-Mail: ${email}
${company ? `Unternehmen: ${company}` : ''}
${phone ? `Telefon: ${phone}` : ''}

Nachricht:
${message}

---
Gesendet am: ${new Date().toLocaleString('de-DE', { 
  timeZone: 'Europe/Berlin',
  dateStyle: 'long',
  timeStyle: 'short'
 })}
    `.trim()

    // Send email
    await transporter.sendMail({
      from: process.env.SMTP_FROM || process.env.SMTP_USER,
      to: 'kontakt@praesenzwert.de',
      subject: `Neue Kontaktanfrage: ${name}`,
      text: emailContent,
      replyTo: email,
    })

    return NextResponse.json(
      { success: true, message: 'Anfrage erfolgreich gesendet' },
      { status: 200 }
    )

  } catch (error) {
    console.error('Error sending email:', error)
    return NextResponse.json(
      { error: 'Fehler beim Senden der Anfrage. Bitte versuchen Sie es später erneut.' },
      { status: 500 }
    )
  }
}
