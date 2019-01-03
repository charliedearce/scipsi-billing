<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmchangepass
    Inherits DevExpress.XtraEditors.XtraForm

    'Form overrides dispose to clean up the component list.
    <System.Diagnostics.DebuggerNonUserCode()> _
    Protected Overrides Sub Dispose(ByVal disposing As Boolean)
        If disposing AndAlso components IsNot Nothing Then
            components.Dispose()
        End If
        MyBase.Dispose(disposing)
    End Sub

    'Required by the Windows Form Designer
    Private components As System.ComponentModel.IContainer

    'NOTE: The following procedure is required by the Windows Form Designer
    'It can be modified using the Windows Form Designer.  
    'Do not modify it using the code editor.
    <System.Diagnostics.DebuggerStepThrough()> _
    Private Sub InitializeComponent()
        Me.Label3 = New System.Windows.Forms.Label()
        Me.txtpassword = New System.Windows.Forms.TextBox()
        Me.Label1 = New System.Windows.Forms.Label()
        Me.Label2 = New System.Windows.Forms.Label()
        Me.txtretypepass = New System.Windows.Forms.TextBox()
        Me.btnUserDel = New System.Windows.Forms.Button()
        Me.SuspendLayout()
        '
        'Label3
        '
        Me.Label3.AutoSize = True
        Me.Label3.Font = New System.Drawing.Font("Segoe UI Semibold", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label3.Location = New System.Drawing.Point(36, 9)
        Me.Label3.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label3.Name = "Label3"
        Me.Label3.Size = New System.Drawing.Size(264, 28)
        Me.Label3.TabIndex = 8
        Me.Label3.Text = "CHANGE PASSWORD FORM"
        '
        'txtpassword
        '
        Me.txtpassword.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtpassword.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtpassword.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtpassword.ForeColor = System.Drawing.Color.Purple
        Me.txtpassword.Location = New System.Drawing.Point(18, 69)
        Me.txtpassword.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.txtpassword.MaxLength = 10
        Me.txtpassword.Name = "txtpassword"
        Me.txtpassword.PasswordChar = Global.Microsoft.VisualBasic.ChrW(42)
        Me.txtpassword.Size = New System.Drawing.Size(301, 45)
        Me.txtpassword.TabIndex = 1
        Me.txtpassword.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'Label1
        '
        Me.Label1.AutoSize = True
        Me.Label1.Font = New System.Drawing.Font("Segoe UI Light", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label1.Location = New System.Drawing.Point(13, 37)
        Me.Label1.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label1.Name = "Label1"
        Me.Label1.Size = New System.Drawing.Size(138, 28)
        Me.Label1.TabIndex = 9
        Me.Label1.Text = "New Password:"
        '
        'Label2
        '
        Me.Label2.AutoSize = True
        Me.Label2.Font = New System.Drawing.Font("Segoe UI Light", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label2.Location = New System.Drawing.Point(8, 118)
        Me.Label2.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label2.Name = "Label2"
        Me.Label2.Size = New System.Drawing.Size(157, 28)
        Me.Label2.TabIndex = 11
        Me.Label2.Text = "Retype Password:"
        '
        'txtretypepass
        '
        Me.txtretypepass.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtretypepass.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtretypepass.Font = New System.Drawing.Font("Segoe UI", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtretypepass.ForeColor = System.Drawing.Color.Purple
        Me.txtretypepass.Location = New System.Drawing.Point(18, 150)
        Me.txtretypepass.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.txtretypepass.MaxLength = 10
        Me.txtretypepass.Name = "txtretypepass"
        Me.txtretypepass.PasswordChar = Global.Microsoft.VisualBasic.ChrW(42)
        Me.txtretypepass.Size = New System.Drawing.Size(301, 45)
        Me.txtretypepass.TabIndex = 2
        Me.txtretypepass.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'btnUserDel
        '
        Me.btnUserDel.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnUserDel.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btnUserDel.Location = New System.Drawing.Point(95, 207)
        Me.btnUserDel.Margin = New System.Windows.Forms.Padding(4, 4, 4, 4)
        Me.btnUserDel.Name = "btnUserDel"
        Me.btnUserDel.Size = New System.Drawing.Size(146, 29)
        Me.btnUserDel.TabIndex = 3
        Me.btnUserDel.Text = "Change Password"
        Me.btnUserDel.UseVisualStyleBackColor = True
        '
        'frmchangepass
        '
        Me.Appearance.BackColor = System.Drawing.Color.White
        Me.Appearance.Options.UseBackColor = True
        Me.Appearance.Options.UseFont = True
        Me.AutoScaleDimensions = New System.Drawing.SizeF(7.0!, 17.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.ClientSize = New System.Drawing.Size(336, 244)
        Me.Controls.Add(Me.btnUserDel)
        Me.Controls.Add(Me.Label2)
        Me.Controls.Add(Me.txtretypepass)
        Me.Controls.Add(Me.Label1)
        Me.Controls.Add(Me.Label3)
        Me.Controls.Add(Me.txtpassword)
        Me.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.FormBorderStyle = System.Windows.Forms.FormBorderStyle.None
        Me.Name = "frmchangepass"
        Me.ShowInTaskbar = False
        Me.StartPosition = System.Windows.Forms.FormStartPosition.CenterParent
        Me.Text = "frmchangepass"
        Me.ResumeLayout(False)
        Me.PerformLayout()

    End Sub
    Friend WithEvents Label3 As System.Windows.Forms.Label
    Friend WithEvents txtpassword As System.Windows.Forms.TextBox
    Friend WithEvents Label1 As System.Windows.Forms.Label
    Friend WithEvents Label2 As System.Windows.Forms.Label
    Friend WithEvents txtretypepass As System.Windows.Forms.TextBox
    Friend WithEvents btnUserDel As System.Windows.Forms.Button
End Class
