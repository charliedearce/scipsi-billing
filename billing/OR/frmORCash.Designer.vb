<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmORCash
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
        Me.TabControl1 = New System.Windows.Forms.TabControl()
        Me.TabPage1 = New System.Windows.Forms.TabPage()
        Me.GroupBox6 = New System.Windows.Forms.GroupBox()
        Me.txtCash = New System.Windows.Forms.TextBox()
        Me.GroupBox1 = New System.Windows.Forms.GroupBox()
        Me.txtTotalDue = New System.Windows.Forms.TextBox()
        Me.TabPage2 = New System.Windows.Forms.TabPage()
        Me.GroupBox3 = New System.Windows.Forms.GroupBox()
        Me.txtCheckno = New System.Windows.Forms.TextBox()
        Me.GroupBox4 = New System.Windows.Forms.GroupBox()
        Me.txtChkTotalDue = New System.Windows.Forms.TextBox()
        Me.GroupBox5 = New System.Windows.Forms.GroupBox()
        Me.Label3 = New System.Windows.Forms.Label()
        Me.TabControl1.SuspendLayout()
        Me.TabPage1.SuspendLayout()
        Me.GroupBox6.SuspendLayout()
        Me.GroupBox1.SuspendLayout()
        Me.TabPage2.SuspendLayout()
        Me.GroupBox3.SuspendLayout()
        Me.GroupBox4.SuspendLayout()
        Me.GroupBox5.SuspendLayout()
        Me.SuspendLayout()
        '
        'TabControl1
        '
        Me.TabControl1.Controls.Add(Me.TabPage1)
        Me.TabControl1.Controls.Add(Me.TabPage2)
        Me.TabControl1.Location = New System.Drawing.Point(6, 19)
        Me.TabControl1.Multiline = True
        Me.TabControl1.Name = "TabControl1"
        Me.TabControl1.SelectedIndex = 0
        Me.TabControl1.Size = New System.Drawing.Size(507, 248)
        Me.TabControl1.SizeMode = System.Windows.Forms.TabSizeMode.Fixed
        Me.TabControl1.TabIndex = 0
        '
        'TabPage1
        '
        Me.TabPage1.Controls.Add(Me.GroupBox6)
        Me.TabPage1.Controls.Add(Me.GroupBox1)
        Me.TabPage1.Font = New System.Drawing.Font("Arial Narrow", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.TabPage1.Location = New System.Drawing.Point(4, 22)
        Me.TabPage1.Name = "TabPage1"
        Me.TabPage1.Padding = New System.Windows.Forms.Padding(3)
        Me.TabPage1.Size = New System.Drawing.Size(499, 222)
        Me.TabPage1.TabIndex = 0
        Me.TabPage1.Text = "CASH"
        Me.TabPage1.UseVisualStyleBackColor = True
        '
        'GroupBox6
        '
        Me.GroupBox6.Controls.Add(Me.txtCash)
        Me.GroupBox6.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox6.Location = New System.Drawing.Point(6, 112)
        Me.GroupBox6.Name = "GroupBox6"
        Me.GroupBox6.Size = New System.Drawing.Size(487, 100)
        Me.GroupBox6.TabIndex = 115
        Me.GroupBox6.TabStop = False
        Me.GroupBox6.Text = "CASH AMOUNT"
        '
        'txtCash
        '
        Me.txtCash.BackColor = System.Drawing.Color.White
        Me.txtCash.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtCash.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtCash.Font = New System.Drawing.Font("Segoe UI", 27.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtCash.ForeColor = System.Drawing.Color.Green
        Me.txtCash.Location = New System.Drawing.Point(6, 37)
        Me.txtCash.MaxLength = 30
        Me.txtCash.Name = "txtCash"
        Me.txtCash.ReadOnly = True
        Me.txtCash.Size = New System.Drawing.Size(475, 50)
        Me.txtCash.TabIndex = 1
        Me.txtCash.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'GroupBox1
        '
        Me.GroupBox1.Controls.Add(Me.txtTotalDue)
        Me.GroupBox1.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox1.Location = New System.Drawing.Point(6, 6)
        Me.GroupBox1.Name = "GroupBox1"
        Me.GroupBox1.Size = New System.Drawing.Size(487, 100)
        Me.GroupBox1.TabIndex = 113
        Me.GroupBox1.TabStop = False
        Me.GroupBox1.Text = "TOTAL DUE"
        '
        'txtTotalDue
        '
        Me.txtTotalDue.BackColor = System.Drawing.Color.White
        Me.txtTotalDue.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtTotalDue.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtTotalDue.Font = New System.Drawing.Font("Segoe UI", 27.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtTotalDue.ForeColor = System.Drawing.Color.Red
        Me.txtTotalDue.Location = New System.Drawing.Point(6, 37)
        Me.txtTotalDue.MaxLength = 30
        Me.txtTotalDue.Name = "txtTotalDue"
        Me.txtTotalDue.ReadOnly = True
        Me.txtTotalDue.Size = New System.Drawing.Size(475, 50)
        Me.txtTotalDue.TabIndex = 112
        Me.txtTotalDue.TabStop = False
        Me.txtTotalDue.Text = "0000000000000000000"
        Me.txtTotalDue.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'TabPage2
        '
        Me.TabPage2.Controls.Add(Me.GroupBox3)
        Me.TabPage2.Controls.Add(Me.GroupBox4)
        Me.TabPage2.Font = New System.Drawing.Font("Arial Narrow", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.TabPage2.Location = New System.Drawing.Point(4, 22)
        Me.TabPage2.Name = "TabPage2"
        Me.TabPage2.Padding = New System.Windows.Forms.Padding(3)
        Me.TabPage2.Size = New System.Drawing.Size(499, 222)
        Me.TabPage2.TabIndex = 1
        Me.TabPage2.Text = "CHECK"
        Me.TabPage2.UseVisualStyleBackColor = True
        '
        'GroupBox3
        '
        Me.GroupBox3.Controls.Add(Me.txtCheckno)
        Me.GroupBox3.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox3.Location = New System.Drawing.Point(6, 112)
        Me.GroupBox3.Name = "GroupBox3"
        Me.GroupBox3.Size = New System.Drawing.Size(487, 100)
        Me.GroupBox3.TabIndex = 116
        Me.GroupBox3.TabStop = False
        Me.GroupBox3.Text = "CHECK NUMBER"
        '
        'txtCheckno
        '
        Me.txtCheckno.BackColor = System.Drawing.Color.White
        Me.txtCheckno.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtCheckno.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtCheckno.Font = New System.Drawing.Font("Segoe UI", 27.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtCheckno.ForeColor = System.Drawing.Color.Black
        Me.txtCheckno.Location = New System.Drawing.Point(6, 37)
        Me.txtCheckno.MaxLength = 30
        Me.txtCheckno.Name = "txtCheckno"
        Me.txtCheckno.Size = New System.Drawing.Size(475, 50)
        Me.txtCheckno.TabIndex = 112
        Me.txtCheckno.TabStop = False
        Me.txtCheckno.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'GroupBox4
        '
        Me.GroupBox4.Controls.Add(Me.txtChkTotalDue)
        Me.GroupBox4.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox4.Location = New System.Drawing.Point(6, 6)
        Me.GroupBox4.Name = "GroupBox4"
        Me.GroupBox4.Size = New System.Drawing.Size(487, 100)
        Me.GroupBox4.TabIndex = 115
        Me.GroupBox4.TabStop = False
        Me.GroupBox4.Text = "TOTAL DUE"
        '
        'txtChkTotalDue
        '
        Me.txtChkTotalDue.BackColor = System.Drawing.Color.White
        Me.txtChkTotalDue.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtChkTotalDue.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtChkTotalDue.Font = New System.Drawing.Font("Segoe UI", 27.75!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtChkTotalDue.ForeColor = System.Drawing.Color.Red
        Me.txtChkTotalDue.Location = New System.Drawing.Point(6, 37)
        Me.txtChkTotalDue.MaxLength = 30
        Me.txtChkTotalDue.Name = "txtChkTotalDue"
        Me.txtChkTotalDue.ReadOnly = True
        Me.txtChkTotalDue.Size = New System.Drawing.Size(475, 50)
        Me.txtChkTotalDue.TabIndex = 112
        Me.txtChkTotalDue.TabStop = False
        Me.txtChkTotalDue.Text = "0000000000000000000"
        Me.txtChkTotalDue.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'GroupBox5
        '
        Me.GroupBox5.Controls.Add(Me.TabControl1)
        Me.GroupBox5.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox5.Location = New System.Drawing.Point(13, 33)
        Me.GroupBox5.Name = "GroupBox5"
        Me.GroupBox5.Size = New System.Drawing.Size(519, 279)
        Me.GroupBox5.TabIndex = 1
        Me.GroupBox5.TabStop = False
        Me.GroupBox5.Text = "PAYMENT METHOD"
        '
        'Label3
        '
        Me.Label3.AutoSize = True
        Me.Label3.Font = New System.Drawing.Font("Segoe UI Semibold", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label3.Location = New System.Drawing.Point(229, 9)
        Me.Label3.Name = "Label3"
        Me.Label3.Size = New System.Drawing.Size(86, 21)
        Me.Label3.TabIndex = 6
        Me.Label3.Text = "PAY FORM"
        '
        'frmORCash
        '
        Me.AutoScaleDimensions = New System.Drawing.SizeF(6.0!, 13.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.BackColor = System.Drawing.Color.White
        Me.ClientSize = New System.Drawing.Size(545, 328)
        Me.ControlBox = False
        Me.Controls.Add(Me.Label3)
        Me.Controls.Add(Me.GroupBox5)
        Me.FormBorderStyle = System.Windows.Forms.FormBorderStyle.None
        Me.MaximizeBox = False
        Me.MinimizeBox = False
        Me.Name = "frmORCash"
        Me.ShowIcon = False
        Me.ShowInTaskbar = False
        Me.StartPosition = System.Windows.Forms.FormStartPosition.CenterScreen
        Me.Text = "PAY FORM"
        Me.TabControl1.ResumeLayout(False)
        Me.TabPage1.ResumeLayout(False)
        Me.GroupBox6.ResumeLayout(False)
        Me.GroupBox6.PerformLayout()
        Me.GroupBox1.ResumeLayout(False)
        Me.GroupBox1.PerformLayout()
        Me.TabPage2.ResumeLayout(False)
        Me.GroupBox3.ResumeLayout(False)
        Me.GroupBox3.PerformLayout()
        Me.GroupBox4.ResumeLayout(False)
        Me.GroupBox4.PerformLayout()
        Me.GroupBox5.ResumeLayout(False)
        Me.ResumeLayout(False)
        Me.PerformLayout()

    End Sub
    Friend WithEvents TabControl1 As System.Windows.Forms.TabControl
    Friend WithEvents TabPage1 As System.Windows.Forms.TabPage
    Friend WithEvents TabPage2 As System.Windows.Forms.TabPage
    Friend WithEvents GroupBox1 As System.Windows.Forms.GroupBox
    Friend WithEvents txtTotalDue As System.Windows.Forms.TextBox
    Friend WithEvents GroupBox3 As System.Windows.Forms.GroupBox
    Friend WithEvents txtCheckno As System.Windows.Forms.TextBox
    Friend WithEvents GroupBox4 As System.Windows.Forms.GroupBox
    Friend WithEvents txtChkTotalDue As System.Windows.Forms.TextBox
    Friend WithEvents GroupBox5 As System.Windows.Forms.GroupBox
    Friend WithEvents GroupBox6 As System.Windows.Forms.GroupBox
    Friend WithEvents txtCash As System.Windows.Forms.TextBox
    Friend WithEvents Label3 As System.Windows.Forms.Label
End Class
