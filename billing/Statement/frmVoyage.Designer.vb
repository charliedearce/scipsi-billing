<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmVoyage
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
        Me.Label7 = New System.Windows.Forms.Label()
        Me.lblbillno = New System.Windows.Forms.Label()
        Me.Label3 = New System.Windows.Forms.Label()
        Me.txtVoyage = New System.Windows.Forms.MaskedTextBox()
        Me.txtPOnum = New System.Windows.Forms.MaskedTextBox()
        Me.Label1 = New System.Windows.Forms.Label()
        Me.Label2 = New System.Windows.Forms.Label()
        Me.SuspendLayout()
        '
        'Label7
        '
        Me.Label7.AutoSize = True
        Me.Label7.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label7.Location = New System.Drawing.Point(12, 30)
        Me.Label7.Name = "Label7"
        Me.Label7.Size = New System.Drawing.Size(79, 13)
        Me.Label7.TabIndex = 15
        Me.Label7.Text = "BILL NUMBER:"
        '
        'lblbillno
        '
        Me.lblbillno.AutoSize = True
        Me.lblbillno.Font = New System.Drawing.Font("Segoe UI", 14.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblbillno.ForeColor = System.Drawing.Color.Red
        Me.lblbillno.Location = New System.Drawing.Point(51, 43)
        Me.lblbillno.Name = "lblbillno"
        Me.lblbillno.Size = New System.Drawing.Size(70, 25)
        Me.lblbillno.TabIndex = 14
        Me.lblbillno.Text = "Label2"
        '
        'Label3
        '
        Me.Label3.AutoSize = True
        Me.Label3.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label3.Location = New System.Drawing.Point(13, 67)
        Me.Label3.Name = "Label3"
        Me.Label3.Size = New System.Drawing.Size(101, 13)
        Me.Label3.TabIndex = 13
        Me.Label3.Text = "VOYAGE NUMBER:"
        '
        'txtVoyage
        '
        Me.txtVoyage.BackColor = System.Drawing.Color.White
        Me.txtVoyage.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtVoyage.Font = New System.Drawing.Font("Segoe UI", 14.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtVoyage.ForeColor = System.Drawing.Color.Purple
        Me.txtVoyage.Location = New System.Drawing.Point(42, 85)
        Me.txtVoyage.Mask = "##########"
        Me.txtVoyage.Name = "txtVoyage"
        Me.txtVoyage.Size = New System.Drawing.Size(91, 26)
        Me.txtVoyage.TabIndex = 16
        Me.txtVoyage.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'txtPOnum
        '
        Me.txtPOnum.BackColor = System.Drawing.Color.White
        Me.txtPOnum.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtPOnum.Font = New System.Drawing.Font("Segoe UI", 14.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtPOnum.ForeColor = System.Drawing.Color.Purple
        Me.txtPOnum.Location = New System.Drawing.Point(12, 135)
        Me.txtPOnum.Mask = "###############"
        Me.txtPOnum.Name = "txtPOnum"
        Me.txtPOnum.Size = New System.Drawing.Size(151, 26)
        Me.txtPOnum.TabIndex = 18
        Me.txtPOnum.TextAlign = System.Windows.Forms.HorizontalAlignment.Center
        '
        'Label1
        '
        Me.Label1.AutoSize = True
        Me.Label1.Font = New System.Drawing.Font("Segoe UI", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label1.Location = New System.Drawing.Point(13, 117)
        Me.Label1.Name = "Label1"
        Me.Label1.Size = New System.Drawing.Size(80, 13)
        Me.Label1.TabIndex = 17
        Me.Label1.Text = "P.O. NUMBER:"
        '
        'Label2
        '
        Me.Label2.AutoSize = True
        Me.Label2.Font = New System.Drawing.Font("Segoe UI Semibold", 12.0!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label2.Location = New System.Drawing.Point(11, 9)
        Me.Label2.Name = "Label2"
        Me.Label2.Size = New System.Drawing.Size(153, 21)
        Me.Label2.TabIndex = 34
        Me.Label2.Text = "EDIT VOYAGE/P.O. #"
        '
        'frmVoyage
        '
        Me.AutoScaleDimensions = New System.Drawing.SizeF(6.0!, 13.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.BackColor = System.Drawing.Color.White
        Me.ClientSize = New System.Drawing.Size(175, 169)
        Me.ControlBox = False
        Me.Controls.Add(Me.Label2)
        Me.Controls.Add(Me.txtPOnum)
        Me.Controls.Add(Me.Label1)
        Me.Controls.Add(Me.txtVoyage)
        Me.Controls.Add(Me.Label7)
        Me.Controls.Add(Me.lblbillno)
        Me.Controls.Add(Me.Label3)
        Me.FormBorderStyle = System.Windows.Forms.FormBorderStyle.None
        Me.MaximizeBox = False
        Me.MinimizeBox = False
        Me.Name = "frmVoyage"
        Me.ShowInTaskbar = False
        Me.StartPosition = System.Windows.Forms.FormStartPosition.CenterParent
        Me.Text = "EDIT FORM"
        Me.ResumeLayout(False)
        Me.PerformLayout()

    End Sub
    Friend WithEvents Label7 As System.Windows.Forms.Label
    Friend WithEvents lblbillno As System.Windows.Forms.Label
    Friend WithEvents Label3 As System.Windows.Forms.Label
    Friend WithEvents txtVoyage As System.Windows.Forms.MaskedTextBox
    Friend WithEvents txtPOnum As System.Windows.Forms.MaskedTextBox
    Friend WithEvents Label1 As System.Windows.Forms.Label
    Friend WithEvents Label2 As System.Windows.Forms.Label
End Class
