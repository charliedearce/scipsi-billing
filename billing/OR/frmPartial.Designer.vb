<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmPartial
    Inherits System.Windows.Forms.Form

    'Form overrides dispose to clean up the component list.
    <System.Diagnostics.DebuggerNonUserCode()> _
    Protected Overrides Sub Dispose(ByVal disposing As Boolean)
        Try
            If disposing AndAlso components IsNot Nothing Then
                components.Dispose()
            End If
        Finally
            MyBase.Dispose(disposing)
        End Try
    End Sub

    'Required by the Windows Form Designer
    Private components As System.ComponentModel.IContainer

    'NOTE: The following procedure is required by the Windows Form Designer
    'It can be modified using the Windows Form Designer.  
    'Do not modify it using the code editor.
    <System.Diagnostics.DebuggerStepThrough()> _
    Private Sub InitializeComponent()
        Me.txtBal = New System.Windows.Forms.TextBox()
        Me.Label2 = New System.Windows.Forms.Label()
        Me.htax = New System.Windows.Forms.TextBox()
        Me.Label1 = New System.Windows.Forms.Label()
        Me.amt = New System.Windows.Forms.TextBox()
        Me.Label3 = New System.Windows.Forms.Label()
        Me.vat = New System.Windows.Forms.TextBox()
        Me.Label4 = New System.Windows.Forms.Label()
        Me.txtbillno = New System.Windows.Forms.TextBox()
        Me.Label5 = New System.Windows.Forms.Label()
        Me.txtNet = New System.Windows.Forms.TextBox()
        Me.txtindiPartial = New System.Windows.Forms.TextBox()
        Me.txtStatus = New System.Windows.Forms.Label()
        Me.Label6 = New System.Windows.Forms.Label()
        Me.SuspendLayout()
        '
        'txtBal
        '
        Me.txtBal.BorderStyle = System.Windows.Forms.BorderStyle.FixedSingle
        Me.txtBal.Font = New System.Drawing.Font("Microsoft Sans Serif", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtBal.Location = New System.Drawing.Point(120, 79)
        Me.txtBal.Name = "txtBal"
        Me.txtBal.Size = New System.Drawing.Size(39, 26)
        Me.txtBal.TabIndex = 21
        Me.txtBal.Text = "0"
        Me.txtBal.Visible = False
        '
        'Label2
        '
        Me.Label2.AutoSize = True
        Me.Label2.BackColor = System.Drawing.Color.Transparent
        Me.Label2.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label2.Location = New System.Drawing.Point(16, 176)
        Me.Label2.Name = "Label2"
        Me.Label2.Size = New System.Drawing.Size(110, 13)
        Me.Label2.TabIndex = 20
        Me.Label2.Text = "WITH HOLDING TAX:"
        '
        'htax
        '
        Me.htax.BackColor = System.Drawing.Color.White
        Me.htax.BorderStyle = System.Windows.Forms.BorderStyle.FixedSingle
        Me.htax.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.htax.Location = New System.Drawing.Point(16, 192)
        Me.htax.MaxLength = 10
        Me.htax.Name = "htax"
        Me.htax.Size = New System.Drawing.Size(165, 29)
        Me.htax.TabIndex = 3
        Me.htax.Text = "0"
        '
        'Label1
        '
        Me.Label1.AutoSize = True
        Me.Label1.BackColor = System.Drawing.Color.Transparent
        Me.Label1.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label1.Location = New System.Drawing.Point(16, 131)
        Me.Label1.Name = "Label1"
        Me.Label1.Size = New System.Drawing.Size(141, 13)
        Me.Label1.TabIndex = 19
        Me.Label1.Text = "AMOUNT (VAT INCLUSIVE):"
        '
        'amt
        '
        Me.amt.BackColor = System.Drawing.Color.White
        Me.amt.BorderStyle = System.Windows.Forms.BorderStyle.FixedSingle
        Me.amt.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.amt.Location = New System.Drawing.Point(16, 147)
        Me.amt.MaxLength = 10
        Me.amt.Name = "amt"
        Me.amt.ReadOnly = True
        Me.amt.Size = New System.Drawing.Size(165, 29)
        Me.amt.TabIndex = 2
        Me.amt.Text = "0"
        '
        'Label3
        '
        Me.Label3.AutoSize = True
        Me.Label3.BackColor = System.Drawing.Color.Transparent
        Me.Label3.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label3.Location = New System.Drawing.Point(16, 86)
        Me.Label3.Name = "Label3"
        Me.Label3.Size = New System.Drawing.Size(29, 13)
        Me.Label3.TabIndex = 18
        Me.Label3.Text = "VAT:"
        '
        'vat
        '
        Me.vat.BackColor = System.Drawing.Color.White
        Me.vat.BorderStyle = System.Windows.Forms.BorderStyle.FixedSingle
        Me.vat.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.vat.Location = New System.Drawing.Point(16, 102)
        Me.vat.MaxLength = 10
        Me.vat.Name = "vat"
        Me.vat.Size = New System.Drawing.Size(165, 29)
        Me.vat.TabIndex = 1
        Me.vat.Text = "0"
        '
        'Label4
        '
        Me.Label4.AutoSize = True
        Me.Label4.BackColor = System.Drawing.Color.Transparent
        Me.Label4.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label4.Location = New System.Drawing.Point(16, 41)
        Me.Label4.Name = "Label4"
        Me.Label4.Size = New System.Drawing.Size(40, 13)
        Me.Label4.TabIndex = 23
        Me.Label4.Text = "BILL #:"
        '
        'txtbillno
        '
        Me.txtbillno.BackColor = System.Drawing.Color.White
        Me.txtbillno.BorderStyle = System.Windows.Forms.BorderStyle.FixedSingle
        Me.txtbillno.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtbillno.Location = New System.Drawing.Point(16, 57)
        Me.txtbillno.MaxLength = 10
        Me.txtbillno.Name = "txtbillno"
        Me.txtbillno.Size = New System.Drawing.Size(165, 29)
        Me.txtbillno.TabIndex = 0
        '
        'Label5
        '
        Me.Label5.AutoSize = True
        Me.Label5.BackColor = System.Drawing.Color.Transparent
        Me.Label5.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label5.Location = New System.Drawing.Point(16, 221)
        Me.Label5.Name = "Label5"
        Me.Label5.Size = New System.Drawing.Size(100, 13)
        Me.Label5.TabIndex = 25
        Me.Label5.Text = "PARTIAL AMOUNT:"
        '
        'txtNet
        '
        Me.txtNet.BackColor = System.Drawing.Color.White
        Me.txtNet.BorderStyle = System.Windows.Forms.BorderStyle.FixedSingle
        Me.txtNet.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtNet.Location = New System.Drawing.Point(16, 237)
        Me.txtNet.MaxLength = 10
        Me.txtNet.Name = "txtNet"
        Me.txtNet.Size = New System.Drawing.Size(165, 29)
        Me.txtNet.TabIndex = 24
        Me.txtNet.Text = "0"
        '
        'txtindiPartial
        '
        Me.txtindiPartial.BorderStyle = System.Windows.Forms.BorderStyle.FixedSingle
        Me.txtindiPartial.Font = New System.Drawing.Font("Microsoft Sans Serif", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtindiPartial.Location = New System.Drawing.Point(66, 79)
        Me.txtindiPartial.Name = "txtindiPartial"
        Me.txtindiPartial.Size = New System.Drawing.Size(39, 26)
        Me.txtindiPartial.TabIndex = 26
        Me.txtindiPartial.Visible = False
        '
        'txtStatus
        '
        Me.txtStatus.AutoSize = True
        Me.txtStatus.ForeColor = System.Drawing.Color.Red
        Me.txtStatus.Location = New System.Drawing.Point(64, 41)
        Me.txtStatus.Name = "txtStatus"
        Me.txtStatus.Size = New System.Drawing.Size(0, 13)
        Me.txtStatus.TabIndex = 27
        '
        'Label6
        '
        Me.Label6.AutoSize = True
        Me.Label6.Font = New System.Drawing.Font("Segoe UI Semibold", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label6.Location = New System.Drawing.Point(5, 9)
        Me.Label6.Name = "Label6"
        Me.Label6.Size = New System.Drawing.Size(186, 21)
        Me.Label6.TabIndex = 28
        Me.Label6.Text = "PARTIAL TRANSACTION"
        '
        'frmPartial
        '
        Me.AutoScaleDimensions = New System.Drawing.SizeF(6.0!, 13.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.BackColor = System.Drawing.Color.White
        Me.ClientSize = New System.Drawing.Size(197, 280)
        Me.ControlBox = False
        Me.Controls.Add(Me.Label6)
        Me.Controls.Add(Me.txtStatus)
        Me.Controls.Add(Me.txtindiPartial)
        Me.Controls.Add(Me.Label5)
        Me.Controls.Add(Me.txtNet)
        Me.Controls.Add(Me.Label4)
        Me.Controls.Add(Me.txtbillno)
        Me.Controls.Add(Me.txtBal)
        Me.Controls.Add(Me.Label2)
        Me.Controls.Add(Me.htax)
        Me.Controls.Add(Me.Label1)
        Me.Controls.Add(Me.amt)
        Me.Controls.Add(Me.Label3)
        Me.Controls.Add(Me.vat)
        Me.FormBorderStyle = System.Windows.Forms.FormBorderStyle.None
        Me.MaximizeBox = False
        Me.MinimizeBox = False
        Me.Name = "frmPartial"
        Me.ShowIcon = False
        Me.ShowInTaskbar = False
        Me.StartPosition = System.Windows.Forms.FormStartPosition.CenterScreen
        Me.Text = "PARTIAL TRANSACTION"
        Me.ResumeLayout(False)
        Me.PerformLayout()

    End Sub
    Friend WithEvents txtBal As System.Windows.Forms.TextBox
    Friend WithEvents Label2 As System.Windows.Forms.Label
    Friend WithEvents htax As System.Windows.Forms.TextBox
    Friend WithEvents Label1 As System.Windows.Forms.Label
    Friend WithEvents amt As System.Windows.Forms.TextBox
    Friend WithEvents Label3 As System.Windows.Forms.Label
    Friend WithEvents vat As System.Windows.Forms.TextBox
    Friend WithEvents Label4 As System.Windows.Forms.Label
    Friend WithEvents txtbillno As System.Windows.Forms.TextBox
    Friend WithEvents Label5 As System.Windows.Forms.Label
    Friend WithEvents txtNet As System.Windows.Forms.TextBox
    Friend WithEvents txtindiPartial As System.Windows.Forms.TextBox
    Friend WithEvents txtStatus As System.Windows.Forms.Label
    Friend WithEvents Label6 As System.Windows.Forms.Label
End Class
